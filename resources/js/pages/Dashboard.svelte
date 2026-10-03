<script module lang="ts">
    import { dashboard } from '@/routes';
    import { index as auditLogs } from '@/routes/audit-logs';
    import { index as roles } from '@/routes/roles';
    import { edit as systemSettings } from '@/routes/system-settings';
    import { index as users } from '@/routes/users';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import FileText from '@lucide/svelte/icons/file-text';
    import Plus from '@lucide/svelte/icons/plus';
    import Receipt from '@lucide/svelte/icons/receipt';
    import ScrollText from '@lucide/svelte/icons/scroll-text';
    import Settings from '@lucide/svelte/icons/settings';
    import UserPlus from '@lucide/svelte/icons/user-plus';
    import Users from '@lucide/svelte/icons/users';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';

    type QuickAction = {
        title: string;
        description: string;
        href?: string;
        permission: string;
        phase?: string;
    };

    const permissions = $derived(
        (page.props.auth?.user?.permission_names ?? []) as string[],
    );

    const settings = $derived(page.props.settings as Record<string, unknown>);

    const quickActions: QuickAction[] = [
        {
            title: 'New Borrower',
            description: 'Register a borrower',
            permission: 'borrowers.create',
            phase: 'Phase 2',
        },
        {
            title: 'New Loan',
            description: 'Encode a loan',
            permission: 'loans.create',
            phase: 'Phase 4',
        },
        {
            title: 'Record Collection',
            description: 'Post a daily collection',
            permission: 'payments.create',
            phase: 'Phase 7',
        },
        {
            title: 'Notes Collection Report',
            description: 'Weekly collections',
            permission: 'reports.view',
            phase: 'Phase 8',
        },
        {
            title: 'Progress Report',
            description: 'Monthly portfolio',
            permission: 'reports.view',
            phase: 'Phase 8',
        },
    ];

    const visibleActions = $derived(
        quickActions.filter((action) => permissions.includes(action.permission)),
    );

    const modules = [
        { name: 'Borrowers', phase: 'Phase 2', done: false },
        { name: 'Loan products and deduction types', phase: 'Phase 3', done: false },
        { name: 'Loan encoding', phase: 'Phase 4', done: false },
        { name: 'Amortization engine', phase: 'Phase 6', done: false },
        { name: 'Collections and payments', phase: 'Phase 7', done: false },
        { name: 'Reporting', phase: 'Phase 8', done: false },
    ];
</script>


<AppHead title="Dashboard" />

<div class="flex flex-1 flex-col gap-4 p-4">
    <div class="grid gap-4 xl:grid-cols-3">
        <Card class="xl:col-span-2">
            <CardHeader>
                <CardTitle>Operational metrics</CardTitle>
                <CardDescription>
                    Today's expected collection, actual collection,
                    outstanding portfolio and past due loans appear here once
                    loans are released.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {#each ['Expected today', 'Collected today', 'Shortfall', 'Outstanding portfolio'] as metric (metric)}
                        <div class="rounded-lg border border-border/70 p-3">
                            <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {metric}
                            </p>
                            <p class="mt-2 text-xl font-semibold tabular-nums text-muted-foreground/60">
                                —
                            </p>
                        </div>
                    {/each}
                </div>
                <p class="mt-4 text-sm text-muted-foreground">
                    No loans have been released yet. The metrics above are
                    calculated from the loan and payment tables and stay empty
                    until the loan encoding screens are in place.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Business</CardTitle>
                <CardDescription>
                    Configured in Settings &gt; System.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                <div class="flex justify-between gap-2">
                    <span class="text-muted-foreground">Business name</span>
                    <span class="font-medium">{settings?.business_name ?? '—'}</span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-muted-foreground">Currency</span>
                    <span class="font-medium">{settings?.currency ?? '—'}</span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-muted-foreground">Loan numbers</span>
                    <span class="font-mono text-xs">{settings?.loan_number_format ?? '—'}</span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-muted-foreground">Borrower numbers</span>
                    <span class="font-mono text-xs">
                        {settings?.borrower_number_format ?? '—'}
                    </span>
                </div>
                {#if permissions.includes('settings.manage')}
                    <Button variant="outline" size="sm" class="mt-2 w-full" asChild>
                        {#snippet children(props)}
                            <Link href={systemSettings()} class={props.class}>
                                <Settings class="size-4" />
                                System settings
                            </Link>
                        {/snippet}
                    </Button>
                {/if}
            </CardContent>
        </Card>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <Card class="xl:col-span-2">
            <CardHeader>
                <CardTitle>Quick actions</CardTitle>
                <CardDescription>
                    Actions available with your permissions.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-2 sm:grid-cols-2">
                {#each visibleActions as action (action.title)}
                    <div
                        class="flex items-center justify-between gap-2 rounded-lg border border-border/70 px-3 py-2"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{action.title}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {action.description}
                            </p>
                        </div>
                        {#if action.href}
                            <Button size="sm" variant="ghost" asChild>
                                {#snippet children(props)}
                                    <Link href={action.href} class={props.class}>
                                        <Plus class="size-4" />
                                        Open
                                    </Link>
                                {/snippet}
                            </Button>
                        {:else}
                            <Badge variant="secondary">{action.phase}</Badge>
                        {/if}
                    </div>
                {/each}
                {#if permissions.includes('users.manage')}
                    <div
                        class="flex items-center justify-between gap-2 rounded-lg border border-border/70 px-3 py-2"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">New User</p>
                            <p class="truncate text-xs text-muted-foreground">
                                Create an account and assign roles
                            </p>
                        </div>
                        <Button size="sm" variant="ghost" asChild>
                            {#snippet children(props)}
                                <Link href={users()} class={props.class}>
                                    <UserPlus class="size-4" />
                                    Open
                                </Link>
                            {/snippet}
                        </Button>
                    </div>
                {/if}
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Delivery status</CardTitle>
                <CardDescription>
                    Modules are built in the order described in AGENTS.md.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-2">
                <div class="flex items-center justify-between rounded-lg border border-border/70 px-3 py-2">
                    <span class="flex items-center gap-2 text-sm font-medium">
                        <Receipt class="size-4" />
                        Foundation
                    </span>
                    <Badge>Ready</Badge>
                </div>
                {#each modules as module (module.name)}
                    <div
                        class="flex items-center justify-between rounded-lg border border-border/70 px-3 py-2"
                    >
                        <span class="flex items-center gap-2 text-sm text-muted-foreground">
                            <FileText class="size-4" />
                            {module.name}
                        </span>
                        <Badge variant="secondary">{module.phase}</Badge>
                    </div>
                {/each}
                {#if permissions.includes('audit.view')}
                    <Button variant="outline" size="sm" class="mt-2 w-full" asChild>
                        {#snippet children(props)}
                            <Link href={auditLogs()} class={props.class}>
                                <ScrollText class="size-4" />
                                Audit logs
                            </Link>
                        {/snippet}
                    </Button>
                {/if}
                {#if permissions.includes('roles.manage')}
                    <Button variant="outline" size="sm" class="w-full" asChild>
                        {#snippet children(props)}
                            <Link href={roles()} class={props.class}>
                                <Users class="size-4" />
                                Roles
                            </Link>
                        {/snippet}
                    </Button>
                {/if}
            </CardContent>
        </Card>
    </div>
</div>
