<script module lang="ts">
    import { index as loansIndex } from '@/routes/loans';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Loans',
                href: loansIndex(),
            },
        ],
    };
</script>

<script lang="ts">
    import { untrack } from 'svelte';
    import { Link, page, router } from '@inertiajs/svelte';
    import Banknote from '@lucide/svelte/icons/banknote';
    import AppHead from '@/components/AppHead.svelte';
    import SearchInput from '@/components/SearchInput.svelte';
    import LoanAreaSummary from '@/components/LoanAreaSummary.svelte';
    import TablePagination from '@/components/TablePagination.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import {
        Select,
        SelectContent,
        SelectGroup,
        SelectItem,
        SelectLabel,
        SelectTrigger,
    } from '@/components/ui/select';
    import {
        Table,
        TableBody,
        TableCell,
        TableHead,
        TableHeader,
        TableRow,
    } from '@/components/ui/table';
    import { create, index, show as showLoan } from '@/routes/loans';
    import type { Paginated } from '@/types';

    type LoanRow = {
        id: string;
        loan_number: string;
        status: string;
        status_label: string;
        borrower: {
            id: string;
            borrower_number: string;
            full_name: string;
        };
        loan_product: {
            name: string;
            code: string;
        };
        gross_loan_amount: string;
        net_proceeds_amount: string;
        total_amount_payable: string;
        application_date: string | null;
        release_date: string | null;
    };

    let {
        loans,
        filters,
        statuses,
        areas,
        groupedLoansByArea,
    }: {
        loans: Paginated<LoanRow>;
        filters: { search?: string | null; status?: string | null; area?: string | null };
        statuses: { value: string; label: string }[];
        areas: { id: string; code: string; name: string }[];
        groupedLoansByArea: Record<string, number>;
    } = $props();

    let search = $state(untrack(() => filters.search ?? ''));
    let status = $state(untrack(() => filters.status ?? 'all'));

    const permissions = $derived(
        (page.props.auth?.user?.permission_names ?? []) as string[],
    );
    const canCreate = $derived(permissions.includes('loans.create'));

    function visit(options: { search?: string; status?: string; area?: string }): void {
        router.get(
            index({
                query: {
                    search: options.search || undefined,
                    status:
                        options.status && options.status !== 'all'
                            ? options.status
                            : undefined,
                    area: options.area || undefined,
                },
            }),
            { preserveState: true, replace: true },
        );
    }

    function changeStatus(value: string): void {
        status = value;
        visit({ search, status: value });
    }

    function changeArea(value: string): void {
        visit({ search, status, area: value === 'all' ? undefined : value });
    }

    function submitSearch(event: Event): void {
        event.preventDefault();

        visit({ search, status });
    }
</script>

<AppHead title="Loans" />

<h1 class="sr-only">Loans</h1>

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-xl font-semibold tracking-tight">Loans</h2>
            <p class="text-sm text-muted-foreground">
                Every loan encoded by this branch, from draft to active.
            </p>
        </div>

        {#if canCreate}
            <Button size="sm" asChild>
                {#snippet children(props)}
                    <Link href={create()} class={props.class}>
                        <Banknote class="size-4" />
                        New loan
                    </Link>
                {/snippet}
            </Button>
        {/if}
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <form onsubmit={submitSearch} class="w-full max-w-sm">
            <SearchInput
                bind:value={search}
                name="search"
                placeholder="Search loan number or borrower"
                ariaLabel="Search loans"
            />
        </form>

        <Select value={status} onValueChange={changeStatus}>
            <SelectTrigger id="status" class="w-52">
                {statuses.find((option) => option.value === status)?.label ??
                    'All statuses'}
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All statuses</SelectItem>
                {#each statuses as option (option.value)}
                    <SelectItem value={option.value}>{option.label}</SelectItem>
                {/each}
            </SelectContent>
        </Select>

        <Select value={filters.area ?? 'all'} onValueChange={changeArea}>
            <SelectTrigger id="area" class="w-52">
                {areas.find((option) => option.id === (filters.area ?? ''))?.name ??
                    'All areas'}
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All areas</SelectItem>
                <SelectGroup>
                    <SelectLabel>Areas</SelectLabel>
                    {#each areas as area (area.id)}
                        <SelectItem value={area.id}>{area.name}</SelectItem>
                    {/each}
                </SelectGroup>
            </SelectContent>
        </Select>
    </div>

    <LoanAreaSummary {groupedLoansByArea} />

    <div class="rounded-lg border border-border/70">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Loan #</TableHead>
                    <TableHead>Borrower</TableHead>
                    <TableHead>Product</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead class="text-right">Gross</TableHead>
                    <TableHead class="text-right">Total payable</TableHead>
                    <TableHead>Application</TableHead>
                    <TableHead>Released</TableHead>
                    <TableHead class="text-right">Actions</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {#each (loans as Paginated<LoanRow>).data as loan (loan.id)}
                    <TableRow>
                        <TableCell class="font-medium tabular-nums">
                            <Link
                                href={showLoan({ loan: loan.id })}
                                class="hover:underline"
                            >
                                {loan.loan_number}
                            </Link>
                        </TableCell>
                        <TableCell>
                            <div>{loan.borrower.full_name}</div>
                            <div class="font-mono text-xs text-muted-foreground">
                                {loan.borrower.borrower_number}
                            </div>
                        </TableCell>
                        <TableCell>
                            <div>{loan.loan_product.name}</div>
                            <div class="font-mono text-xs text-muted-foreground">
                                {loan.loan_product.code}
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant={loan.status === 'active' ? 'secondary' : 'outline'}
                            >
                                {loan.status_label}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {loan.gross_loan_amount}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {loan.total_amount_payable}
                        </TableCell>
                        <TableCell class="tabular-nums text-muted-foreground">
                            {loan.application_date ?? '—'}
                        </TableCell>
                        <TableCell class="tabular-nums text-muted-foreground">
                            {loan.release_date ?? '—'}
                        </TableCell>
                        <TableCell class="text-right">
                            <Button variant="outline" size="sm" asChild>
                                {#snippet children(props)}
                                    <Link
                                        href={showLoan({ loan: loan.id })}
                                        class={props.class}
                                    >
                                        View
                                    </Link>
                                {/snippet}
                            </Button>
                        </TableCell>
                    </TableRow>
                {:else}
                    <TableRow>
                        <TableCell
                            colspan={9}
                            class="h-24 text-center text-muted-foreground"
                        >
                            No loans found.
                        </TableCell>
                    </TableRow>
                {/each}
            </TableBody>
        </Table>
    </div>

    <TablePagination meta={loans as Paginated<LoanRow>} />
</div>
