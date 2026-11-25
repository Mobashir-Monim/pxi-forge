<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Archive, ShieldCheck, SquarePen, Users } from '@lucide/vue';
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
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { show as showChat } from '@/routes/chats';
import { index as rolesIndex } from '@/routes/roles';
import { index as usersIndex } from '@/routes/users';
import type { ChatSummary, NavItem } from '@/types';

const can = useCan();
const page = usePage();
const { isCurrentUrl } = useCurrentUrl();
const chats = computed(
    () => (page.props.chats as ChatSummary[] | undefined) ?? [],
);

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

            <SidebarGroup class="group-data-[collapsible=icon]:hidden">
                <SidebarGroupLabel>Recents</SidebarGroupLabel>
                <SidebarMenu v-if="chats.length">
                    <SidebarMenuItem v-for="chat in chats" :key="chat.id">
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl(showChat(chat.id))"
                            :tooltip="chat.title"
                        >
                            <Link :href="showChat(chat.id)">
                                <span class="truncate">{{ chat.title }}</span>
                                <Archive
                                    v-if="chat.archived"
                                    class="ml-auto text-muted-foreground"
                                    aria-label="Closed"
                                />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <p v-else class="px-2 text-sm text-muted-foreground">
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
