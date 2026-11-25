<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, index, store, update } from '@/routes/roles';

type CatalogEntry = { name: string; label: string; description: string };

const props = defineProps<{
    role: {
        id: number;
        name: string;
        is_system: boolean;
        is_super_admin: boolean;
        permissions: string[];
    } | null;
    catalog: Record<string, CatalogEntry[]>;
    grantable: string[];
    can: { update: boolean; delete: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles', href: index() }],
    },
});

const form = useForm({
    name: props.role?.name ?? '',
    permissions: [...(props.role?.permissions ?? [])],
});

const title = computed(() => props.role?.name ?? 'New role');

// A permission the user doesn't hold can't be granted, so its box stays locked.
function locked(permission: string): boolean {
    return !props.can.update || !props.grantable.includes(permission);
}

function checked(permission: string): boolean {
    return props.role?.is_super_admin || form.permissions.includes(permission);
}

function toggle(permission: string, on: boolean | 'indeterminate'): void {
    form.permissions =
        on === true
            ? [...form.permissions, permission]
            : form.permissions.filter((p) => p !== permission);
}

function setGroup(entries: CatalogEntry[], on: boolean): void {
    const names = entries.map((e) => e.name).filter((n) => !locked(n));
    const rest = form.permissions.filter((p) => !names.includes(p));
    form.permissions = on ? [...rest, ...names] : rest;
}

function submit(): void {
    form.submit(props.role ? update(props.role) : store(), {
        preserveScroll: true,
    });
}

function remove(): void {
    if (props.role) {
        router.visit(destroy(props.role));
    }
}
</script>

<template>
    <Head :title="title" />

    <form class="flex max-w-3xl flex-col gap-8 p-4" @submit.prevent="submit">
        <Heading
            :title="title"
            :description="
                role
                    ? 'Choose exactly what this role allows. Each action is its own permission.'
                    : 'Name the role, then pick the permissions it grants.'
            "
        />

        <Alert v-if="role?.is_super_admin">
            <Lock />
            <AlertDescription>
                Super Admin passes every permission check, including ones added
                later, so it can't be edited or deleted.
            </AlertDescription>
        </Alert>
        <Alert v-else-if="role && !can.update">
            <Lock />
            <AlertDescription>
                You can view this role but not change it. Editing a role needs
                the "Edit roles" permission and every permission the role
                grants.
            </AlertDescription>
        </Alert>

        <div class="grid max-w-sm gap-2">
            <Label for="name">Name</Label>
            <Input
                id="name"
                v-model="form.name"
                required
                maxlength="50"
                :disabled="!can.update || role?.is_system"
            />
            <p v-if="role?.is_system" class="text-sm text-muted-foreground">
                System roles keep their name.
            </p>
            <InputError :message="form.errors.name" />
        </div>

        <div class="flex flex-col gap-6">
            <section
                v-for="(entries, group) in catalog"
                :key="group"
                class="rounded-lg border"
            >
                <header
                    class="flex items-center justify-between border-b px-4 py-2"
                >
                    <h3 class="font-medium">{{ group }}</h3>
                    <div
                        v-if="can.update && !role?.is_super_admin"
                        class="flex gap-1"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="setGroup(entries, true)"
                            >All</Button
                        >
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="setGroup(entries, false)"
                            >None</Button
                        >
                    </div>
                </header>
                <ul class="divide-y">
                    <li v-for="entry in entries" :key="entry.name">
                        <Label
                            :for="entry.name"
                            class="flex items-start gap-3 px-4 py-3 font-normal"
                            :class="{ 'opacity-60': locked(entry.name) }"
                        >
                            <Checkbox
                                :id="entry.name"
                                class="mt-0.5"
                                :model-value="checked(entry.name)"
                                :disabled="locked(entry.name)"
                                @update:model-value="toggle(entry.name, $event)"
                            />
                            <span class="grid gap-0.5">
                                <span class="font-medium">{{
                                    entry.label
                                }}</span>
                                <span class="text-sm text-muted-foreground">
                                    {{ entry.description }}
                                    <code class="text-xs">{{
                                        entry.name
                                    }}</code>
                                </span>
                            </span>
                        </Label>
                    </li>
                </ul>
            </section>
            <InputError
                :message="
                    form.errors.permissions ??
                    Object.entries(form.errors).find(([k]) =>
                        k.startsWith('permissions.'),
                    )?.[1]
                "
            />
        </div>

        <div v-if="can.update || can.delete" class="flex items-center gap-4">
            <Button v-if="can.update" type="submit" :disabled="form.processing">
                {{ role ? 'Save role' : 'Create role' }}
            </Button>

            <Dialog v-if="role && can.delete">
                <DialogTrigger as-child>
                    <Button type="button" variant="destructive"
                        >Delete role</Button
                    >
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete {{ role.name }}?</DialogTitle>
                        <DialogDescription>
                            Everyone holding this role loses it, and every
                            permission it gave them. This can't be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button variant="destructive" @click="remove"
                            >Delete role</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </form>
</template>
