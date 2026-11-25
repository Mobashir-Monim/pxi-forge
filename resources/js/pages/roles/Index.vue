<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Plus } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/composables/useCan';
import { create, index, show } from '@/routes/roles';

type RoleRow = {
    id: number;
    name: string;
    is_system: boolean;
    is_super_admin: boolean;
    permissions_count: number;
    users_count: number;
};

defineProps<{ roles: RoleRow[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles', href: index() }],
    },
});

const can = useCan();
</script>

<template>
    <Head title="Roles" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Roles"
                description="A role is a set of permissions. Users can hold several roles and get every permission in them."
            />
            <Button v-if="can('roles.create')" as-child>
                <Link :href="create()"><Plus /> New role</Link>
            </Button>
        </div>

        <ul class="divide-y rounded-lg border">
            <li v-for="role in roles" :key="role.id">
                <Link
                    :href="show(role)"
                    class="flex items-center gap-4 px-4 py-3 hover:bg-muted/50"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 font-medium">
                            {{ role.name }}
                            <Badge v-if="role.is_system" variant="secondary"
                                >System</Badge
                            >
                        </div>
                        <p class="text-sm text-muted-foreground">
                            <template v-if="role.is_super_admin"
                                >Every permission, always</template
                            >
                            <template v-else
                                >{{ role.permissions_count }}
                                {{
                                    role.permissions_count === 1
                                        ? 'permission'
                                        : 'permissions'
                                }}</template
                            >
                            ·
                            {{ role.users_count }}
                            {{ role.users_count === 1 ? 'user' : 'users' }}
                        </p>
                    </div>
                    <ChevronRight class="size-4 text-muted-foreground" />
                </Link>
            </li>
        </ul>
    </div>
</template>
