<script module lang="ts">
    import { index as auditLogsIndex } from '@/routes/audit-logs';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Audit logs',
                href: auditLogsIndex(),
            },
        ],
    };
</script>

<script lang="ts">
    import { untrack } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import SearchInput from '@/components/SearchInput.svelte';
    import TablePagination from '@/components/TablePagination.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Table,
        TableBody,
        TableCell,
        TableHead,
        TableHeader,
        TableRow,
    } from '@/components/ui/table';
    import type { Paginated } from '@/types';

    type AuditLogRow = {
        id: number;
        action: string;
        user: string | null;
        record: string | null;
        old_values: Record<string, unknown> | null;
        new_values: Record<string, unknown> | null;
        reason: string | null;
        ip_address: string | null;
        created_at: string | null;
    };

    let {
        logs,
        filters,
        actions,
    }: {
        logs: Paginated<AuditLogRow>;
        filters: Record<string, string | null>;
        actions: string[];
    } = $props();

    let action = $state(untrack(() => filters.action ?? ''));
    let user = $state(untrack(() => filters.user ?? ''));
    let from = $state(untrack(() => filters.from ?? ''));
    let to = $state(untrack(() => filters.to ?? ''));

    function submitFilters(event: Event): void {
        event.preventDefault();

        router.get(
            auditLogsIndex({
                query: {
                    action: action || undefined,
                    user: user || undefined,
                    from: from || undefined,
                    to: to || undefined,
                },
            }),
            { preserveState: true, replace: true },
        );
    }

    function summarise(values: Record<string, unknown> | null): string {
        if (!values) {
            return '—';
        }

        return Object.entries(values)
            .map(([key, value]) => `${key}: ${JSON.stringify(value)}`)
            .join(', ');
    }
</script>

<AppHead title="Audit logs" />

<datalist id="audit-actions">
    {#each actions as action (action)}
        <option value={action}></option>
    {/each}
</datalist>

<h1 class="sr-only">Audit logs</h1>

<div class="flex flex-col gap-4 p-4">
    <div>
        <h2 class="text-xl font-semibold tracking-tight">Audit logs</h2>
        <p class="text-sm text-muted-foreground">
            Append only history of who changed what, when and why. Entries are
            never edited or deleted.
        </p>
    </div>

    <form onsubmit={submitFilters} class="grid gap-3 sm:grid-cols-5">
        <div class="grid gap-1">
            <Label for="action">Action</Label>
            <SearchInput
                bind:value={action}
                name="action"
                placeholder="settings."
                list="audit-actions"
            />
        </div>

        <div class="grid gap-1">
            <Label for="user">User</Label>
            <SearchInput bind:value={user} name="user" placeholder="Name" />
        </div>

        <div class="grid gap-1">
            <Label for="from">From</Label>
            <Input id="from" bind:value={from} name="from" type="date" />
        </div>

        <div class="grid gap-1">
            <Label for="to">To</Label>
            <Input id="to" bind:value={to} name="to" type="date" />
        </div>

        <div class="flex items-end">
            <Button type="submit" size="sm">Filter</Button>
        </div>
    </form>

    <div class="rounded-lg border border-border/70">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>When</TableHead>
                    <TableHead>Action</TableHead>
                    <TableHead>User</TableHead>
                    <TableHead>Record</TableHead>
                    <TableHead>Old values</TableHead>
                    <TableHead>New values</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {#each logs.data as log (log.id)}
                    <TableRow>
                        <TableCell class="whitespace-nowrap tabular-nums text-muted-foreground">
                            {log.created_at}
                        </TableCell>
                        <TableCell>
                            <Badge variant="secondary">{log.action}</Badge>
                        </TableCell>
                        <TableCell>{log.user ?? 'system'}</TableCell>
                        <TableCell class="text-muted-foreground">
                            {log.record ?? '—'}
                        </TableCell>
                        <TableCell class="max-w-xs truncate font-mono text-xs text-muted-foreground">
                            {summarise(log.old_values)}
                        </TableCell>
                        <TableCell class="max-w-xs truncate font-mono text-xs text-muted-foreground">
                            {summarise(log.new_values)}
                        </TableCell>
                    </TableRow>
                {:else}
                    <TableRow>
                        <TableCell colspan={6} class="h-24 text-center text-muted-foreground">
                            No audit entries yet.
                        </TableCell>
                    </TableRow>
                {/each}
            </TableBody>
        </Table>
    </div>

    <TablePagination meta={logs} />
</div>
