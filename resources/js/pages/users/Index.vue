<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/users';
import { update as updateRoles } from '@/routes/users/roles';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role_ids: number[];
    is_self: boolean;
};

type RoleOption = { id: number; name: string; within_reach: boolean };

const props = defineProps<{
    users: {
        data: UserRow[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    roles: RoleOption[];
    can: { assign_roles: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

const roleName = computed(
    () => new Map(props.roles.map((role) => [role.id, role.name])),
);

const editing = ref<UserRow | null>(null);
const form = useForm<{ roles: number[] }>({ roles: [] });

function edit(user: UserRow): void {
    form.clearErrors();
    form.roles = [...user.role_ids];
    editing.value = user;
}

function toggle(id: number, on: boolean | 'indeterminate'): void {
    form.roles =
        on === true ? [...form.roles, id] : form.roles.filter((r) => r !== id);
}

function save(): void {
    if (!editing.value) {
        return;
    }

    form.submit(updateRoles(editing.value), {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
}
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Users"
            description="Everyone with an account and the roles they hold."
        />

        <ul class="divide-y rounded-lg border">
            <li
                v-for="user in users.data"
                :key="user.id"
                class="flex items-center gap-4 px-4 py-3"
            >
                <div class="min-w-0 flex-1">
                    <div class="font-medium">
                        {{ user.name }}
                        <span
                            v-if="user.is_self"
                            class="text-sm font-normal text-muted-foreground"
                            >(you)</span
                        >
                    </div>
                    <div class="truncate text-sm text-muted-foreground">
                        {{ user.email }}
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-1">
                    <Badge
                        v-for="id in user.role_ids"
                        :key="id"
                        variant="secondary"
                        >{{ roleName.get(id) }}</Badge
                    >
                    <span
                        v-if="user.role_ids.length === 0"
                        class="text-sm text-muted-foreground"
                        >No roles</span
                    >
                </div>
                <Button
                    v-if="can.assign_roles && !user.is_self"
                    variant="outline"
                    size="sm"
                    @click="edit(user)"
                    >Edit roles</Button
                >
            </li>
        </ul>

        <div
            v-if="users.prev_page_url || users.next_page_url"
            class="flex gap-2"
        >
            <Button
                v-if="users.prev_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="users.prev_page_url">Previous</Link>
            </Button>
            <Button
                v-if="users.next_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="users.next_page_url">Next</Link>
            </Button>
        </div>
    </div>

    <Dialog
        :open="editing !== null"
        @update:open="(open) => !open && (editing = null)"
    >
        <DialogContent v-if="editing">
            <DialogHeader>
                <DialogTitle>Roles for {{ editing.name }}</DialogTitle>
                <DialogDescription>
                    They get every permission in every role ticked here. Roles
                    granting something you don't hold are locked.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-3">
                <Label
                    v-for="role in roles"
                    :key="role.id"
                    :for="`role-${role.id}`"
                    class="flex items-center gap-3 font-normal"
                    :class="{ 'opacity-60': !role.within_reach }"
                >
                    <Checkbox
                        :id="`role-${role.id}`"
                        :model-value="form.roles.includes(role.id)"
                        :disabled="!role.within_reach"
                        @update:model-value="toggle(role.id, $event)"
                    />
                    {{ role.name }}
                </Label>
                <InputError :message="form.errors.roles" />
            </div>

            <DialogFooter class="gap-2">
                <Button variant="secondary" @click="editing = null"
                    >Cancel</Button
                >
                <Button :disabled="form.processing" @click="save"
                    >Save roles</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
