<script module lang="ts">
    import { create as createLoan } from '@/routes/loans';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Loans',
                href: createLoan(),
            },
            {
                title: 'Loan Review',
                href: createLoan(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import {
        Table,
        TableBody,
        TableCell,
        TableFooter,
        TableHead,
        TableHeader,
        TableRow,
    } from '@/components/ui/table';
    import { create as loansCreate } from '@/routes/loans';
    import { schedule as loanSchedule } from '@/routes/loans';
    import Download from '@lucide/svelte/icons/download';
    import Printer from '@lucide/svelte/icons/printer';

    type Installment = {
        installment_number: number;
        due_date: string;
        principal_due: string;
        interest_due: string;
        total_due: string;
        paid_amount: string;
        remaining_amount: string;
        status: string;
    };

    type LoanReview = {
        id: string;
        loan_number: string;
        status: string;
        application_date: string | null;
        release_date: string | null;
        borrower: {
            id: string;
            borrower_number: string;
            full_name: string;
        };
        loan_product: {
            id: string;
            name: string;
            code: string;
        };
        gross_loan_amount: string;
        interest_amount: string;
        deductions_amount: string;
        net_proceeds_amount: string;
        total_amount_payable: string;
        created_at: string | null;
        amortization: Installment[];
    };

    type BalanceSummary = {
        total_amount_payable: string;
        total_amount_paid: string;
        outstanding_balance: string;
        unapplied_credit: string;
        past_due_amount: string;
    };

    type PaymentRow = {
        id: string;
        payment_number: string;
        payment_date: string;
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
        loan,
        balance,
        payments,
    }: {
        loan: LoanReview;
        balance: BalanceSummary | null;
        payments: PaymentRow[];
    } = $props();

    const hasSchedule = $derived(loan.amortization.length > 0);

    const scheduleTotals = $derived(
        loan.amortization.reduce(
            (totals, row) => ({
                principal:
                    totals.principal + Number(row.principal_due),
                interest: totals.interest + Number(row.interest_due),
                total: totals.total + Number(row.total_due),
                remaining:
                    totals.remaining + Number(row.remaining_amount),
            }),
            { principal: 0, interest: 0, total: 0, remaining: 0 },
        ),
    );

    function money(value: number): string {
        return value.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function statusLabel(status: string): string {
        return status.replace(/_/g, ' ');
    }
</script>

<AppHead title={loan.loan_number} />

<h1 class="sr-only">Loan {loan.loan_number}</h1>

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3 print:hidden">
        <div class="grid gap-1">
            <Heading
                variant="small"
                title={loan.loan_number}
                description={hasSchedule
                    ? (loan.status === 'active' ? 'Active loan' : 'Active loan')
                    : 'Draft loan review'}
            />
            <p class="text-sm text-muted-foreground">
                Borrower {loan.borrower.full_name} · {loan.loan_product.name}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <Link href={`/loans/${loan.id}/edit`} class={props.class}>Back to encode</Link>
                {/snippet}
            </Button>
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <Link href={loansCreate()} class={props.class}>New loan</Link>
                {/snippet}
            </Button>
        </div>

        <div class="flex items-center gap-2">
            {#if loan.status === 'draft'}
                <Button variant="outline" onclick={() => router.post(`/loans/${loan.id}/post`)}>
                    Post
                </Button>
            {/if}
            {#if hasSchedule}
                <Button variant="outline" asChild>
                    {#snippet children(props)}
                        <a
                            href={loanSchedule({ loan: loan.id }).url}
                            target="_blank"
                            rel="noopener"
                            class={props.class}
                        >
                            <Printer class="size-4" />
                            Print schedule
                        </a>
                    {/snippet}
                </Button>
                <Button variant="outline" asChild>
                    {#snippet children(props)}
                        <a
                            href={`${loanSchedule({ loan: loan.id }).url}?download=1`}
                            class={props.class}
                        >
                            <Download class="size-4" />
                            PDF
                        </a>
                    {/snippet}
                </Button>
            {/if}
            <Badge variant="secondary">{statusLabel(loan.status)}</Badge>
        </div>
    </div>

    <div class="hidden print:mb-4 print:block">
        <h2 class="text-lg font-semibold">Loan {loan.loan_number}</h2>
        <p class="text-sm">
            {loan.borrower.full_name} · {loan.loan_product.name} · Posted {loan.release_date ?? '—'}
        </p>
    </div>

    <Card>
        <CardHeader>
            <CardTitle>{hasSchedule ? 'Released terms' : 'Saved draft'}</CardTitle>
        </CardHeader>
        <CardContent class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Loan number</p>
                <p class="mt-1 font-mono text-lg font-semibold">{loan.loan_number}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Borrower</p>
                <p class="mt-1 font-medium">{loan.borrower.full_name}</p>
                <p class="text-xs text-muted-foreground">{loan.borrower.borrower_number}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Product</p>
                <p class="mt-1 font-medium">{loan.loan_product.name}</p>
                <p class="text-xs text-muted-foreground">{loan.loan_product.code}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Application date</p>
                <p class="mt-1 font-medium">{loan.application_date ?? '—'}</p>
            </div>
            {#if loan.release_date}
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Release date</p>
                    <p class="mt-1 font-medium">{loan.release_date}</p>
                </div>
            {/if}
        </CardContent>
    </Card>

    <Card>
        <CardHeader>
            <CardTitle>{hasSchedule ? 'Loan amounts' : 'Financial preview'}</CardTitle>
        </CardHeader>
        <CardContent class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <div><p class="text-xs uppercase text-muted-foreground">Gross loan amount</p><p class="mt-1 font-semibold tabular-nums">{loan.gross_loan_amount}</p></div>
            <div><p class="text-xs uppercase text-muted-foreground">Interest</p><p class="mt-1 font-semibold tabular-nums">{loan.interest_amount}</p></div>
            <div><p class="text-xs uppercase text-muted-foreground">Total deductions</p><p class="mt-1 font-semibold tabular-nums">{loan.deductions_amount}</p></div>
            <div><p class="text-xs uppercase text-muted-foreground">Net proceeds</p><p class="mt-1 font-semibold tabular-nums">{loan.net_proceeds_amount}</p></div>
            <div><p class="text-xs uppercase text-muted-foreground">Total amount payable</p><p class="mt-1 font-semibold tabular-nums">{loan.total_amount_payable}</p></div>
            <div><p class="text-xs uppercase text-muted-foreground">{hasSchedule ? 'Installments' : 'Saved at'}</p><p class="mt-1 font-semibold tabular-nums">{hasSchedule ? loan.amortization.length : (loan.created_at ?? '—')}</p></div>
        </CardContent>
    </Card>

    {#if hasSchedule && balance}
        <Card>
            <CardHeader>
                <CardTitle>Balance</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div><p class="text-xs uppercase text-muted-foreground">Total amount payable</p><p class="mt-1 font-semibold tabular-nums">{balance.total_amount_payable}</p></div>
                <div><p class="text-xs uppercase text-muted-foreground">Total amount paid</p><p class="mt-1 font-semibold tabular-nums">{balance.total_amount_paid}</p></div>
                <div><p class="text-xs uppercase text-muted-foreground">Outstanding balance</p><p class="mt-1 font-semibold tabular-nums">{balance.outstanding_balance}</p></div>
                <div><p class="text-xs uppercase text-muted-foreground">Unapplied credit</p><p class="mt-1 font-semibold tabular-nums">{balance.unapplied_credit}</p></div>
                <div><p class="text-xs uppercase text-muted-foreground">Past due</p><p class="mt-1 font-semibold tabular-nums">{balance.past_due_amount}</p></div>
            </CardContent>
        </Card>
    {/if}

    {#if hasSchedule}
        <Card>
            <CardHeader>
                <CardTitle>Payments</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Payment #</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead class="text-right">Amount</TableHead>
                                <TableHead class="text-right">Allocated</TableHead>
                                <TableHead class="text-right">Unapplied</TableHead>
                                <TableHead>Method</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {#each payments as payment (payment.id)}
                                <TableRow>
                                    <TableCell class="font-mono text-xs">{payment.payment_number}</TableCell>
                                    <TableCell class="tabular-nums">{payment.payment_date}</TableCell>
                                    <TableCell class="text-right tabular-nums">{payment.amount}</TableCell>
                                    <TableCell class="text-right tabular-nums">{payment.allocated_amount}</TableCell>
                                    <TableCell class="text-right tabular-nums">{payment.unapplied_amount}</TableCell>
                                    <TableCell class="capitalize">{payment.payment_method}</TableCell>
                                    <TableCell>
                                        <Badge variant={payment.status === 'posted' ? 'secondary' : 'outline'}>
                                            {payment.status_label}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            {:else}
                                <TableRow>
                                    <TableCell colspan={7} class="h-16 text-center text-muted-foreground">
                                        No payments recorded yet.
                                    </TableCell>
                                </TableRow>
                            {/each}
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    {/if}

    {#if hasSchedule}
        <Card>
            <CardHeader>
                <CardTitle>Amortization schedule</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-16 text-right">#</TableHead>
                                <TableHead>Due date</TableHead>
                                <TableHead class="text-right">Principal due</TableHead>
                                <TableHead class="text-right">Interest due</TableHead>
                                <TableHead class="text-right">Total due</TableHead>
                                <TableHead class="text-right">Paid</TableHead>
                                <TableHead class="text-right">Remaining</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {#each loan.amortization as row (row.installment_number)}
                                <TableRow>
                                    <TableCell class="text-right tabular-nums">{row.installment_number}</TableCell>
                                    <TableCell>{row.due_date}</TableCell>
                                    <TableCell class="text-right tabular-nums">{row.principal_due}</TableCell>
                                    <TableCell class="text-right tabular-nums">{row.interest_due}</TableCell>
                                    <TableCell class="text-right tabular-nums font-medium">{row.total_due}</TableCell>
                                    <TableCell class="text-right tabular-nums">{row.paid_amount}</TableCell>
                                    <TableCell class="text-right tabular-nums">{row.remaining_amount}</TableCell>
                                    <TableCell class="capitalize">{statusLabel(row.status)}</TableCell>
                                </TableRow>
                            {/each}
                        </TableBody>
                        <TableFooter>
                            <TableRow>
                                <TableCell colspan={2} class="font-medium">Total</TableCell>
                                <TableCell class="text-right tabular-nums font-semibold">{money(scheduleTotals.principal)}</TableCell>
                                <TableCell class="text-right tabular-nums font-semibold">{money(scheduleTotals.interest)}</TableCell>
                                <TableCell class="text-right tabular-nums font-semibold">{money(scheduleTotals.total)}</TableCell>
                                <TableCell></TableCell>
                                <TableCell class="text-right tabular-nums font-semibold">{money(scheduleTotals.remaining)}</TableCell>
                                <TableCell></TableCell>
                            </TableRow>
                        </TableFooter>
                    </Table>
                </div>
            </CardContent>
        </Card>
    {/if}
</div>
