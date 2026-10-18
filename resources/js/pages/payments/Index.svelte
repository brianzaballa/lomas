<script module lang="ts">
    import { index as paymentsIndex } from '@/routes/payments';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Payments',
                href: paymentsIndex(),
            },
        ],
    };
</script>

<script lang="ts">
    import { untrack } from 'svelte';
    import { Link, page, router } from '@inertiajs/svelte';
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import SearchInput from '@/components/SearchInput.svelte';
    import TablePagination from '@/components/TablePagination.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
        DialogTrigger,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Select,
        SelectContent,
        SelectItem,
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
    import { index, reverse } from '@/routes/payments';
    import { show as showLoan } from '@/routes/loans';
    import type { Paginated } from '@/types';

    type PaymentRow = {
        id: string;
        payment_number: string;
        payment_date: string;
        loan_id: string;
        loan_number: string;
        borrower_name: string;
        amount: string;
        allocated_amount: string;
        unapplied_amount: string;
        payment_method: string;
        reference_number: string | null;
        status: string;
        status_label: string;
        reversal_reason: string | null;
    };

    let {
        payments,
        filters,
        statuses,
    }: {
        payments: Paginated<PaymentRow>;
        filters: {
            search?: string | null;
            status?: string | null;
            from?: string | null;
            to?: string | null;
        };
        statuses: { value: string; label: string }[];
    } = $props();

    let search = $state(untrack(() => filters.search ?? ''));
    let status = $state(untrack(() => filters.status ?? 'all'));
    let from = $state(untrack(() => filters.from ?? ''));
    let to = $state(untrack(() => filters.to ?? ''));

    const permissions = $derived(
        (page.props.auth?.user?.permission_names ?? []) as string[],
    );
    const canReverse = $derived(permissions.includes('payments.reverse'));

    function visit(): void {
        router.get(
            index({
                query: {
                    search: search || undefined,
                    status: status !== 'all' ? status : undefined,
                    from: from || undefined,
                    to: to || undefined,
                },
            }),
            { preserveState: true, replace: true },
        );
    }

    function submitSearch(event: Event): void {
        event.preventDefault();
        visit();
    }
</script>

<AppHead title="Payments" />

<h1 class="sr-only">Payments</h1>

<div class="flex flex-col gap-4 p-4">
    <div>
        <h2 class="text-xl font-semibold tracking-tight">Payments</h2>
        <p class="text-sm text-muted-foreground">
            Every payment recorded by this branch. Posted payments may be reversed, never deleted.
        </p>
    </div>

    <div class="flex flex-wrap items-end gap-2">
        <form onsubmit={submitSearch} class="w-full max-w-sm">
            <SearchInput
                bind:value={search}
                name="search"
                placeholder="Search payment, loan or borrower"
                ariaLabel="Search payments"
            />
        </form>

        <Select
            value={status}
            onValueChange={(value) => {
                status = value;
                visit();
            }}
        >
            <SelectTrigger id="status" class="w-44">
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

        <Input
            type="date"
            bind:value={from}
            class="w-40"
            onchange={visit}
            aria-label="From date"
        />
        <Input
            type="date"
            bind:value={to}
            class="w-40"
            onchange={visit}
            aria-label="To date"
        />
    </div>

    <div class="rounded-lg border border-border/70">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Payment #</TableHead>
                    <TableHead>Date</TableHead>
                    <TableHead>Loan #</TableHead>
                    <TableHead>Borrower</TableHead>
                    <TableHead class="text-right">Amount</TableHead>
                    <TableHead class="text-right">Allocated</TableHead>
                    <TableHead class="text-right">Unapplied</TableHead>
                    <TableHead>Method</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead class="text-right">Actions</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {#each (payments as Paginated<PaymentRow>).data as payment (payment.id)}
                    <TableRow>
                        <TableCell class="font-mono text-xs">
                            {payment.payment_number}
                        </TableCell>
                        <TableCell class="tabular-nums">{payment.payment_date}</TableCell>
                        <TableCell class="font-mono text-xs">
                            <Link
                                href={showLoan({ loan: payment.loan_id })}
                                class="hover:underline"
                            >
                                {payment.loan_number}
                            </Link>
                        </TableCell>
                        <TableCell>{payment.borrower_name}</TableCell>
                        <TableCell class="text-right tabular-nums">
                            {payment.amount}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {payment.allocated_amount}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {payment.unapplied_amount}
                        </TableCell>
                        <TableCell class="capitalize">{payment.payment_method}</TableCell>
                        <TableCell>
                            <Badge
                                variant={payment.status === 'posted'
                                    ? 'secondary'
                                    : 'outline'}
                            >
                                {payment.status_label}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right">
                            {#if canReverse && payment.status === 'posted'}
                                <Dialog>
                                    <DialogTrigger>
                                        <Button variant="outline" size="sm">
                                            Reverse
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <Form
                                            {...reverse.form({ payment: payment.id })}
                                            options={{ preserveScroll: true }}
                                            class="grid gap-4"
                                        >
                                            {#snippet children({ processing })}
                                                <div class="grid gap-1">
                                                    <DialogTitle>
                                                        Reverse {payment.payment_number}
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        This keeps the payment and its audit
                                                        trail. Its allocations stop counting
                                                        toward balances and reports.
                                                    </DialogDescription>
                                                </div>

                                                <div class="grid gap-2">
                                                    <Label for={`reason-${payment.id}`}>
                                                        Reason
                                                    </Label>
                                                    <textarea
                                                        id={`reason-${payment.id}`}
                                                        name="reason"
                                                        required
                                                        minlength={3}
                                                        rows={3}
                                                        class="border-input w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs"
                                                        placeholder="Why is this payment being reversed?"
                                                    ></textarea>
                                                </div>

                                                <DialogFooter class="gap-2">
                                                    <DialogClose>
                                                        <Button variant="secondary" type="button">
                                                            Cancel
                                                        </Button>
                                                    </DialogClose>
                                                    <Button type="submit" disabled={processing}>
                                                        Reverse payment
                                                    </Button>
                                                </DialogFooter>
                                            {/snippet}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            {/if}
                        </TableCell>
                    </TableRow>
                {:else}
                    <TableRow>
                        <TableCell
                            colspan={10}
                            class="h-24 text-center text-muted-foreground"
                        >
                            No payments found.
                        </TableCell>
                    </TableRow>
                {/each}
            </TableBody>
        </Table>
    </div>

    <TablePagination meta={payments as Paginated<PaymentRow>} />
</div>
