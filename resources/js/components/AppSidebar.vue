<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ShieldCheck, SquarePen, Users } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCan } from '@/composables/useCan';
import { dashboard } from '@/routes';
import { index as rolesIndex } from '@/routes/roles';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const can = useCan();

const mainNavItems: NavItem[] = [
    { title: 'New image', href: dashboard(), icon: SquarePen },
];

const adminNavItems = computed<NavItem[]>(() => [
    ...(can('users.view')
        ? [{ title: 'Users', href: usersIndex(), icon: Users }]
        : []),
    ...(can('roles.view')
        ? [{ title: 'Roles', href: rolesIndex(), icon: ShieldCheck }]
        : []),
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain
                v-if="adminNavItems.length"
                label="Admin"
                :items="adminNavItems"
            />

            <!-- ponytail: empty until generations exist, then this lists the latest ones -->
            <SidebarGroup class="group-data-[collapsible=icon]:hidden">
                <SidebarGroupLabel>Recents</SidebarGroupLabel>
                <p class="px-2 text-sm text-muted-foreground">
                    Your images will show up here.
                </p>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
