<script module lang="ts">
    import { index as borrowersIndex } from '@/routes/borrowers';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Borrowers',
                href: borrowersIndex(),
            },
        ],
    };
</script>

<script lang="ts">
    import { untrack } from 'svelte';
    import { Link, page, router } from '@inertiajs/svelte';
    import ArrowDown from '@lucide/svelte/icons/arrow-down';
    import ArrowUp from '@lucide/svelte/icons/arrow-up';
    import UserPlus from '@lucide/svelte/icons/user-plus';
    import AppHead from '@/components/AppHead.svelte';
    import SearchInput from '@/components/SearchInput.svelte';
    import TablePagination from '@/components/TablePagination.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import {
        Table,
        TableBody,
        TableCell,
        TableHead,
        TableHeader,
        TableRow,
    } from '@/components/ui/table';
    import { cn } from '@/lib/utils';
    import {
        create,
        edit,
        index,
        show as showBorrower,
    } from '@/routes/borrowers';
    import type { Paginated } from '@/types';

    type BorrowerRow = {
        id: number;
        borrower_number: string;
        full_name: string;
        mobile_number: string;
        status: string;
        status_label: string;
        created_at: string | null;
    };

    type SortKey = 'borrower_number' | 'name' | 'mobile_number' | 'created_at';

    let {
        borrowers,
        filters,
        sort,
    }: {
        borrowers: Paginated<BorrowerRow>;
        filters: { search?: string | null };
        sort: { by: SortKey; direction: 'asc' | 'desc' };
    } = $props();

    let search = $state(untrack(() => filters.search ?? ''));

    const permissions = $derived(
        (page.props.auth?.user?.permission_names ?? []) as string[],
    );
    const canCreate = $derived(permissions.includes('borrowers.create'));
    const canUpdate = $derived(permissions.includes('borrowers.update'));

    function visit(options: { search?: string; sort?: SortKey; direction?: string }): void {
        router.get(
            index({
                query: {
                    search: options.search || undefined,
                    sort: options.sort,
                    direction: options.direction,
                },
            }),
            { preserveState: true, replace: true },
        );
    }

    function applySort(column: SortKey): void {
        visit({
            search,
            sort: column,
            direction:
                sort.by === column && sort.direction === 'asc' ? 'desc' : 'asc',
        });
    }

    function submitSearch(event: Event): void {
        event.preventDefault();

        visit({ search, sort: sort.by, direction: sort.direction });
    }
</script>

{#snippet sortHeader(column: SortKey, label: string)}
    {@const active = sort.by === column}
    <button
        type="button"
        class="flex items-center gap-1 hover:text-foreground"
        onclick={() => applySort(column)}
    >
        {label}
        {#if active && sort.direction === 'asc'}
            <ArrowUp class="size-3.5" />
        {:else}
            <ArrowDown class={cn('size-3.5', !active && 'opacity-30')} />
        {/if}
    </button>
{/snippet}

<AppHead title="Borrowers" />

<h1 class="sr-only">Borrowers</h1>

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-xl font-semibold tracking-tight">Borrowers</h2>
            <p class="text-sm text-muted-foreground">
                People who borrow from the business. One borrower can hold several
                loans.
            </p>
        </div>

        {#if canCreate}
            <Button size="sm" asChild>
                {#snippet children(props)}
                    <Link href={create()} class={props.class}>
                        <UserPlus class="size-4" />
                        New borrower
                    </Link>
                {/snippet}
            </Button>
        {/if}
    </div>

    <form onsubmit={submitSearch} class="max-w-sm">
        <SearchInput
            bind:value={search}
            name="search"
            placeholder="Search number, name or mobile"
            ariaLabel="Search borrowers"
        />
    </form>

    <div class="rounded-lg border border-border/70">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>
                        {@render sortHeader('borrower_number', 'Borrower #')}
                    </TableHead>
                    <TableHead>{@render sortHeader('name', 'Name')}</TableHead>
                    <TableHead>{@render sortHeader('mobile_number', 'Mobile')}</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead class="text-right">Actions</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {#each (borrowers as Paginated<BorrowerRow>).data as borrower (borrower.id)}
                    <TableRow>
                        <TableCell class="font-medium tabular-nums">
                            <Link
                                href={showBorrower({ borrower: borrower.id })}
                                class="hover:underline"
                            >
                                {borrower.borrower_number}
                            </Link>
                        </TableCell>
                        <TableCell>{borrower.full_name}</TableCell>
                        <TableCell class="tabular-nums text-muted-foreground">
                            {borrower.mobile_number}
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant={borrower.status === 'active'
                                    ? 'secondary'
                                    : 'outline'}
                            >
                                {borrower.status_label}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm" asChild>
                                    {#snippet children(props)}
                                        <Link
                                            href={showBorrower({
                                                borrower: borrower.id,
                                            })}
                                            class={props.class}
                                        >
                                            View
                                        </Link>
                                    {/snippet}
                                </Button>
                                {#if canUpdate}
                                    <Button variant="outline" size="sm" asChild>
                                        {#snippet children(props)}
                                            <Link
                                                href={edit({
                                                    borrower: borrower.id,
                                                })}
                                                class={props.class}
                                            >
                                                Edit
                                            </Link>
                                        {/snippet}
                                    </Button>
                                {/if}
                            </div>
                        </TableCell>
                    </TableRow>
                {:else}
                    <TableRow>
                        <TableCell
                            colspan={5}
                            class="h-24 text-center text-muted-foreground"
                        >
                            No borrowers found.
                        </TableCell>
                    </TableRow>
                {/each}
            </TableBody>
        </Table>
    </div>

    <TablePagination meta={borrowers as Paginated<BorrowerRow>} />
</div>