<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import Banknote from '@lucide/svelte/icons/banknote';
    import Calendar from '@lucide/svelte/icons/calendar';
    import FileText from '@lucide/svelte/icons/file-text';
    import LayoutGrid from '@lucide/svelte/icons/layout-grid';
    import ListChecks from '@lucide/svelte/icons/list-checks';
    import Percent from '@lucide/svelte/icons/percent';
    import Receipt from '@lucide/svelte/icons/receipt';
    import ScrollText from '@lucide/svelte/icons/scroll-text';
    import Settings from '@lucide/svelte/icons/settings';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import UserCog from '@lucide/svelte/icons/user-cog';
    import UserPlus from '@lucide/svelte/icons/user-plus';
    import Users from '@lucide/svelte/icons/users';
    import Wallet from '@lucide/svelte/icons/wallet';
    import type { Component, Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import { index as auditLogs } from '@/routes/audit-logs';
    import {
        create as createBorrower,
        index as borrowers,
    } from '@/routes/borrowers';
    import { index as deductionTypes } from '@/routes/deduction-types';
    import { index as loanProducts } from '@/routes/loan-products';
    import { index as roles } from '@/routes/roles';
    import { edit as systemSettings } from '@/routes/system-settings';
    import { index as users } from '@/routes/users';
    import type { NavEntry } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    type IconComponent = Component<{ class?: string }>;

    const permissions = $derived(
        (page.props.auth?.user?.permission_names ?? []) as string[],
    );

    /**
     * Navigation entry that only appears as a link when the user holds the
     * given permission, and as a disabled entry otherwise.
     */
    function gated(
        title: string,
        icon: IconComponent,
        href: string,
        permission: string,
        group?: string,
    ): NavEntry {
        const allowed = permissions.includes(permission);

        return {
            title,
            icon,
            ...(group ? { group } : {}),
            ...(allowed ? { href } : { disabled: true, badge: 'No access' }),
        };
    }

    const plannedItems: NavEntry[] = [
        {
            group: 'Loans',
            title: 'All Loans',
            icon: Wallet,
            disabled: true,
            badge: 'Phase 4',
        },
        {
            title: 'New Loan',
            icon: Banknote,
            disabled: true,
            badge: 'Phase 4',
        },
        {
            title: 'Active Loans',
            icon: ListChecks,
            disabled: true,
            badge: 'Phase 5',
        },
        {
            title: 'Past Due',
            icon: Calendar,
            disabled: true,
            badge: 'Phase 7',
        },
        {
            title: 'Completed',
            icon: ListChecks,
            disabled: true,
            badge: 'Phase 7',
        },
        {
            group: 'Collections',
            title: 'Daily Collection',
            icon: Receipt,
            disabled: true,
            badge: 'Phase 7',
        },
        {
            title: 'Payments',
            icon: Banknote,
            disabled: true,
            badge: 'Phase 7',
        },
        {
            title: 'Notes Collection Report',
            icon: FileText,
            disabled: true,
            badge: 'Phase 8',
        },
        {
            group: 'Reports',
            title: 'Progress Report',
            icon: FileText,
            disabled: true,
            badge: 'Phase 8',
        },
    ];

    const mainNavItems = $derived<NavEntry[]>([
        {
            group: 'Operations',
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        gated(
            'All Borrowers',
            Users,
            borrowers().url,
            'borrowers.view',
            'Borrowers',
        ),
        gated('New Borrower', UserPlus, createBorrower().url, 'borrowers.create'),
        ...plannedItems,
        gated(
            'Loan Products',
            Percent,
            loanProducts().url,
            'settings.manage',
            'Settings',
        ),
        gated(
            'Deduction Types',
            Receipt,
            deductionTypes().url,
            'settings.manage',
        ),
        gated(
            'System Settings',
            Settings,
            systemSettings().url,
            'settings.manage',
            'Settings',
        ),
        gated(
            'Users',
            UserCog,
            users().url,
            'users.manage',
            'Administration',
        ),
        gated('Roles', ShieldCheck, roles().url, 'roles.manage'),
        gated('Audit Logs', ScrollText, auditLogs().url, 'audit.view'),
    ]);
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(dashboard())}
                            class={props.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter>
        <NavUser />
    </SidebarFooter>
</Sidebar>
