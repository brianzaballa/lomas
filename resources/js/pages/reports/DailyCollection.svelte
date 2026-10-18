<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Reports',
                href: '/reports/daily-collection',
            },
            {
                title: 'Daily Collection Report',
                href: '/reports/daily-collection',
            },
        ],
    };
</script>

<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import {
        Table,
        TableBody,
        TableCell,
        TableFooter,
        TableHead,
        TableHeader,
        TableRow,
    } from '@/components/ui/table';
    import { dailyCollection } from '@/routes/reports';
    import { exportMethod } from '@/routes/reports/daily-collection';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import Download from '@lucide/svelte/icons/download';
    import FileSpreadsheet from '@lucide/svelte/icons/file-spreadsheet';
    import Printer from '@lucide/svelte/icons/printer';

    type PaymentRow = {
        payment_number: string;
        loan_number: string;
        borrower_name: string;
        borrower_number: string;
        payment_method: string;
        payment_method_label: string;
        reference_number: string | null;
        amount: string;
    };

    type AreaRow = {
        area: string;
        count: number;
        total: string;
        payments: PaymentRow[];
    };

    type MethodRow = {
        method: string;
        label: string;
        count: number;
        total: string;
    };

    type Report = {
        date: string;
        weekday: string;
        label: string;
        previous_date: string;
        next_date: string;
        is_today: boolean;
        areas: AreaRow[];
        methods: MethodRow[];
        total: string;
        total_count: number;
    };

    let { report, date }: { report: Report; date: string } = $props();

    const permissions = $derived(
        (page.props.auth?.user?.permission_names ?? []) as string[],
    );
    const canExport = $derived(permissions.includes('reports.export'));

    function go(value: string): void {
        router.get(dailyCollection({ query: { date: value } }), {
            preserveState: false,
        });
    }

    function exportUrl(query: Record<string, string | number>): string {
        return exportMethod({ query: { date: report.date, ...query } }).url;
    }
</script>

<AppHead title="Daily Collection Report" />

<h1 class="sr-only">Daily Collection Report</h1>

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <Heading
                variant="small"
                title="Daily Collection Report"
                description={`${report.weekday}, ${report.label}`}
            />
            <p class="text-sm text-muted-foreground">
                All posted payments for the day, summarised by area.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                variant="outline"
                size="icon"
                onclick={() => go(report.previous_date)}
                aria-label="Previous day"
            >
                <ChevronLeft class="size-4" />
            </Button>
            <Input
                type="date"
                value={date}
                class="w-44"
                onchange={(event) => go(event.currentTarget.value)}
                aria-label="Select date"
            />
            <Button
                variant="outline"
                size="icon"
                onclick={() => go(report.next_date)}
                aria-label="Next day"
            >
                <ChevronRight class="size-4" />
            </Button>

            {#if canExport}
                <Button variant="outline" asChild>
                    {#snippet children(props)}
                        <a href={exportUrl({})} target="_blank" rel="noopener" class={props.class}>
                            <Printer class="size-4" />
                            Print
                        </a>
                    {/snippet}
                </Button>
                <Button variant="outline" asChild>
                    {#snippet children(props)}
                        <a href={exportUrl({ download: 1 })} class={props.class}>
                            <Download class="size-4" />
                            PDF
                        </a>
                    {/snippet}
                </Button>
                <Button variant="outline" asChild>
                    {#snippet children(props)}
                        <a href={exportUrl({ format: 'csv' })} class={props.class}>
                            <FileSpreadsheet class="size-4" />
                            CSV
                        </a>
                    {/snippet}
                </Button>
            {/if}
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <Card>
            <CardHeader>
                <CardTitle class="text-sm text-muted-foreground">Total collected</CardTitle>
            </CardHeader>
            <CardContent class="text-2xl font-semibold tabular-nums">{report.total}</CardContent>
        </Card>
        <Card>
            <CardHeader>
                <CardTitle class="text-sm text-muted-foreground">Payments</CardTitle>
            </CardHeader>
            <CardContent class="text-2xl font-semibold tabular-nums">{report.total_count}</CardContent>
        </Card>
        <Card>
            <CardHeader>
                <CardTitle class="text-sm text-muted-foreground">Areas</CardTitle>
            </CardHeader>
            <CardContent class="text-2xl font-semibold tabular-nums">{report.areas.length}</CardContent>
        </Card>
    </div>

    <Card>
        <CardHeader>
            <CardTitle>Payments by area</CardTitle>
        </CardHeader>
        <CardContent class="grid gap-4">
            {#each report.areas as area (area.area)}
                <div class="rounded-lg border border-border/70">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b border-border/70 bg-muted/40 px-3 py-2"
                    >
                        <span class="text-sm font-semibold uppercase tracking-wide">
                            {area.area}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {area.count}
                            {area.count === 1 ? 'payment' : 'payments'} · {area.total}
                        </span>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Payment #</TableHead>
                                <TableHead>Loan #</TableHead>
                                <TableHead>Client</TableHead>
                                <TableHead>Method</TableHead>
                                <TableHead>Reference</TableHead>
                                <TableHead class="text-right">Amount</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {#each area.payments as payment (payment.payment_number)}
                                <TableRow>
                                    <TableCell class="font-mono text-xs">
                                        {payment.payment_number}
                                    </TableCell>
                                    <TableCell class="font-mono text-xs">
                                        {payment.loan_number}
                                    </TableCell>
                                    <TableCell>{payment.borrower_name}</TableCell>
                                    <TableCell>{payment.payment_method_label}</TableCell>
                                    <TableCell>{payment.reference_number ?? '—'}</TableCell>
                                    <TableCell class="text-right tabular-nums">
                                        {payment.amount}
                                    </TableCell>
                                </TableRow>
                            {/each}
                        </TableBody>
                        <TableFooter>
                            <TableRow>
                                <TableCell colspan={5} class="font-medium">
                                    {area.area} subtotal
                                </TableCell>
                                <TableCell class="text-right font-semibold tabular-nums">
                                    {area.total}
                                </TableCell>
                            </TableRow>
                        </TableFooter>
                    </Table>
                </div>
            {:else}
                <div class="rounded-lg border border-border/70 p-8 text-center text-muted-foreground">
                    No payments were posted on this date.
                </div>
            {/each}

            {#if report.areas.length > 0}
                <div class="flex items-center justify-between rounded-lg border border-border/70 bg-muted/40 px-3 py-2">
                    <span class="text-sm font-medium">Grand total</span>
                    <span class="text-sm font-semibold tabular-nums">
                        {report.total_count}
                        {report.total_count === 1 ? 'payment' : 'payments'} · {report.total}
                    </span>
                </div>
            {/if}
        </CardContent>
    </Card>

    <Card>
        <CardHeader>
            <CardTitle>Collection by payment method</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="rounded-lg border border-border/70">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Method</TableHead>
                            <TableHead class="text-right">Payments</TableHead>
                            <TableHead class="text-right">Amount collected</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {#each report.methods as method (method.method)}
                            <TableRow>
                                <TableCell>{method.label}</TableCell>
                                <TableCell class="text-right tabular-nums">{method.count}</TableCell>
                                <TableCell class="text-right tabular-nums">{method.total}</TableCell>
                            </TableRow>
                        {:else}
                            <TableRow>
                                <TableCell colspan={3} class="h-24 text-center text-muted-foreground">
                                    No payments were posted on this date.
                                </TableCell>
                            </TableRow>
                        {/each}
                    </TableBody>
                    <TableFooter>
                        <TableRow>
                            <TableCell class="font-medium">Total</TableCell>
                            <TableCell class="text-right font-semibold tabular-nums">
                                {report.total_count}
                            </TableCell>
                            <TableCell class="text-right font-semibold tabular-nums">
                                {report.total}
                            </TableCell>
                        </TableRow>
                    </TableFooter>
                </Table>
            </div>
        </CardContent>
    </Card>
</div>
