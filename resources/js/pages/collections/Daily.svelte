<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Daily Collection',
                href: '/collections/daily',
            },
        ],
    };
</script>

<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    import { daily, post, stage } from '@/routes/collections';
    import { destroy as destroyPayment } from '@/routes/collections/payments';
    import { dailyCollection } from '@/routes/reports';
    import { exportMethod } from '@/routes/reports/daily-collection';
    import Printer from '@lucide/svelte/icons/printer';
    import FileText from '@lucide/svelte/icons/file-text';

    type ScheduledRow = {
        loan_id: string;
        loan_number: string;
        borrower_number: string;
        borrower_name: string;
        area: string;
        oldest_due_date: string;
        scheduled_amount: string;
        paid_amount: string;
        remaining_amount: string;
        staged_amount: string;
        staged_payment_id: string | null;
    };

    type PaymentRow = {
        id: string;
        payment_number: string;
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

    type AreaOption = {
        id: string;
        name: string;
    };

    type CollectionSummary = {
        id: string;
        business_date: string;
        status: string;
        is_open: boolean;
        payment_count: number;
        total_amount: string;
        prepared_at: string | null;
        posted_at: string | null;
    };

    let {
        date,
        collection,
        scheduled,
        payments,
        methods,
        filters = { area: null, name: null },
        areas = [],
    }: {
        date: string;
        today: string;
        collection: CollectionSummary | null;
        scheduled: ScheduledRow[];
        payments: PaymentRow[];
        methods: string[];
        filters?: { area: string | null; name: string | null };
        areas?: AreaOption[];
    } = $props();

    const permissions = $derived(
        (page.props.auth?.user?.permission_names ?? []) as string[],
    );
    const canCreate = $derived(permissions.includes('payments.create'));
    const canViewReport = $derived(permissions.includes('reports.view'));
    const canExport = $derived(permissions.includes('reports.export'));

    const money = new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    const isPosted = $derived(collection?.status === 'posted');

    let amounts = $state<Record<string, string>>(
        untrack(() =>
            Object.fromEntries(
                scheduled.map((row) => [row.loan_id, row.staged_amount ?? '']),
            ),
        ),
    );
    let method = $state('cash');
    let reference = $state('');
    let posting = $state(false);

    let areaFilter = $state(untrack(() => filters.area ?? ''));
    let nameFilter = $state(untrack(() => filters.name ?? ''));

    const stagedTotal = $derived(
        scheduled.reduce((total, row) => {
            const value = Number(amounts[row.loan_id] || 0);
            return total + (Number.isFinite(value) ? value : 0);
        }, 0),
    );

    // The payments already saved on the open day, which are what posting sends.
    const staged = $derived(
        payments.filter((payment) => payment.status === 'pending'),
    );

    const stagedSum = $derived(
        staged.reduce((total, payment) => total + Number(payment.amount || 0), 0),
    );

    // Entered amounts that differ from what is saved would be silently left
    // behind by posting, so the confirmation asks for a save first.
    const hasUnsavedChanges = $derived(
        scheduled.some(
            (row) =>
                Number(amounts[row.loan_id] || 0) !==
                Number(row.staged_amount || 0),
        ),
    );

    const reportUrl = $derived(dailyCollection({ query: { date } }).url);
    const printUrl = $derived(exportMethod({ query: { date } }).url);

    function applyFilters(): void {
        router.get(
            daily({
                query: {
                    date,
                    area: areaFilter || undefined,
                    name: nameFilter || undefined,
                },
            }),
            { preserveState: false, preserveScroll: true },
        );
    }

    function changeDate(value: string): void {
        router.get(
            daily({
                query: {
                    date: value,
                    area: areaFilter || undefined,
                    name: nameFilter || undefined,
                },
            }),
            { preserveState: false },
        );
    }

    function clearFilters(): void {
        areaFilter = '';
        nameFilter = '';
        applyFilters();
    }

    function save(): void {
        const entries = scheduled.map((row) => ({
            loan_id: row.loan_id,
            amount: amounts[row.loan_id] || null,
            payment_method: method,
            reference_number: reference || null,
        }));

        router.post(
            stage().url,
            {
                business_date: date,
                entries,
            },
            { preserveScroll: true },
        );
    }

    function postDay(): void {
        router.post(
            post({ query: { date } }).url,
            { date },
            {
                preserveScroll: true,
                onStart: () => (posting = true),
                onFinish: () => (posting = false),
            },
        );
    }

    function removeStaged(paymentId: string): void {
        router.delete(destroyPayment({ payment: paymentId }).url, { preserveScroll: true });
    }
</script>

<AppHead title="Daily collection" />

<h1 class="sr-only">Daily collection</h1>

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-xl font-semibold tracking-tight">Daily collection</h2>
            <p class="text-sm text-muted-foreground">
                Prepare the amounts for a collection day, then post the day as a unit.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <Input
                type="date"
                value={date}
                class="w-44"
                onchange={(event) => changeDate(event.currentTarget.value)}
            />
            {#if collection}
                <Badge variant={isPosted ? 'secondary' : 'outline'}>
                    {isPosted ? 'Posted' : 'Open'}
                </Badge>
            {/if}
            {#if isPosted && canViewReport}
                <Button variant="outline" asChild>
                    {#snippet children(props)}
                        <a href={reportUrl} class={props.class}>
                            <FileText class="size-4" />
                            View report
                        </a>
                    {/snippet}
                </Button>
            {/if}
            {#if isPosted && canExport}
                <Button asChild>
                    {#snippet children(props)}
                        <a href={printUrl} target="_blank" rel="noopener" class={props.class}>
                            <Printer class="size-4" />
                            Print report
                        </a>
                    {/snippet}
                </Button>
            {/if}
        </div>
    </div>

    <Card>
        <CardContent class="flex flex-wrap items-end gap-3 pt-6">
            <div class="grid gap-1">
                <label for="area-filter" class="text-xs font-medium text-muted-foreground">
                    Area
                </label>
                <Select
                    value={areaFilter || 'all'}
                    onValueChange={(value) => {
                        areaFilter = value === 'all' ? '' : value;
                        applyFilters();
                    }}
                >
                    <SelectTrigger id="area-filter" class="w-44">
                        {areas.find((area) => area.id === areaFilter)?.name ?? 'All areas'}
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All areas</SelectItem>
                        {#each areas as area (area.id)}
                            <SelectItem value={area.id}>{area.name}</SelectItem>
                        {/each}
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1">
                <label for="name-filter" class="text-xs font-medium text-muted-foreground">
                    Borrower
                </label>
                <Input
                    id="name-filter"
                    bind:value={nameFilter}
                    placeholder="Name, number or mobile"
                    class="w-64"
                    onkeydown={(event) => {
                        if (event.key === 'Enter') applyFilters();
                    }}
                />
            </div>

            <Button variant="outline" onclick={applyFilters}>Apply</Button>
            <Button variant="ghost" onclick={clearFilters}>Clear</Button>
        </CardContent>
    </Card>

    {#if isPosted}
        <Card>
            <CardHeader>
                <CardTitle>Posted payments · {collection?.total_amount ?? '0.00'}</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-3">
                <div class="rounded-lg border border-border/70">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Payment #</TableHead>
                                <TableHead>Loan #</TableHead>
                                <TableHead>Borrower</TableHead>
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
                                    <TableCell class="font-mono text-xs">
                                        {payment.payment_number}
                                    </TableCell>
                                    <TableCell class="font-mono text-xs">
                                        {payment.loan_number}
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
                                    <TableCell class="capitalize">
                                        {payment.payment_method}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={payment.status === 'reversed'
                                                ? 'outline'
                                                : 'secondary'}
                                        >
                                            {payment.status_label}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            {:else}
                                <TableRow>
                                    <TableCell
                                        colspan={8}
                                        class="h-20 text-center text-muted-foreground"
                                    >
                                        No payments were posted for this day.
                                    </TableCell>
                                </TableRow>
                            {/each}
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    {:else}
        <Card>
            <CardHeader>
                <CardTitle>Loans due on or before {date}</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="grid gap-1">
                        <label
                            for="method"
                            class="text-xs font-medium text-muted-foreground"
                        >
                            Payment method
                        </label>
                        <Select value={method} onValueChange={(value) => (method = value)}>
                            <SelectTrigger id="method" class="w-44">
                                {method}
                            </SelectTrigger>
                            <SelectContent>
                                {#each methods as option (option)}
                                    <SelectItem value={option}>{option}</SelectItem>
                                {/each}
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1">
                        <label
                            for="reference"
                            class="text-xs font-medium text-muted-foreground"
                        >
                            Reference
                        </label>
                        <Input
                            id="reference"
                            bind:value={reference}
                            placeholder="Optional"
                            class="w-56"
                        />
                    </div>
                </div>

                <div class="rounded-lg border border-border/70">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Loan #</TableHead>
                                <TableHead>Borrower</TableHead>
                                <TableHead>Area</TableHead>
                                <TableHead>Oldest due</TableHead>
                                <TableHead class="text-right">Scheduled</TableHead>
                                <TableHead class="text-right">Paid</TableHead>
                                <TableHead class="text-right">Remaining</TableHead>
                                <TableHead class="w-40 text-right">Amount</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {#each scheduled as row (row.loan_id)}
                                <TableRow>
                                    <TableCell class="font-mono text-xs">
                                        {row.loan_number}
                                    </TableCell>
                                    <TableCell>
                                        <div>{row.borrower_name}</div>
                                        <div class="font-mono text-xs text-muted-foreground">
                                            {row.borrower_number}
                                        </div>
                                    </TableCell>
                                    <TableCell>{row.area}</TableCell>
                                    <TableCell class="tabular-nums">
                                        {row.oldest_due_date}
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">
                                        {row.scheduled_amount}
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">
                                        {row.paid_amount}
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">
                                        {row.remaining_amount}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Input
                                            inputmode="decimal"
                                            class="text-right"
                                            value={amounts[row.loan_id] ?? ''}
                                            oninput={(event) =>
                                                (amounts[row.loan_id] =
                                                    event.currentTarget.value)}
                                        />
                                    </TableCell>
                                </TableRow>
                            {:else}
                                <TableRow>
                                    <TableCell
                                        colspan={8}
                                        class="h-20 text-center text-muted-foreground"
                                    >
                                        Nothing is due on this date.
                                    </TableCell>
                                </TableRow>
                            {/each}
                        </TableBody>
                    </Table>
                </div>

                {#if canCreate}
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm text-muted-foreground">
                            Entered total:
                            <span class="font-medium tabular-nums"
                                >{money.format(stagedTotal)}</span
                            >
                        </p>
                        <div class="flex items-center gap-2">
                            <Button variant="outline" onclick={save}>
                                Save amounts
                            </Button>

                            <Dialog>
                                <DialogTrigger>
                                    <Button
                                        disabled={!collection ||
                                            (staged.length === 0 && stagedTotal === 0)}
                                    >
                                        Post collection
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-2xl">
                                    <div class="grid gap-4">
                                        <div class="grid gap-1">
                                            <DialogTitle>
                                                Post collection for {date}
                                            </DialogTitle>
                                            <DialogDescription>
                                                Review what will be posted. Posting closes
                                                the day as a unit; afterwards it can only
                                                be changed by a reversal.
                                            </DialogDescription>
                                        </div>

                                        <div class="grid gap-3 sm:grid-cols-3">
                                            <div class="rounded-lg border border-border/70 p-3">
                                                <div class="text-xs text-muted-foreground">
                                                    Collection day
                                                </div>
                                                <div class="text-sm font-medium tabular-nums">
                                                    {date}
                                                </div>
                                            </div>
                                            <div class="rounded-lg border border-border/70 p-3">
                                                <div class="text-xs text-muted-foreground">
                                                    Payments
                                                </div>
                                                <div class="text-sm font-medium tabular-nums">
                                                    {staged.length}
                                                </div>
                                            </div>
                                            <div class="rounded-lg border border-border/70 p-3">
                                                <div class="text-xs text-muted-foreground">
                                                    Total to post
                                                </div>
                                                <div class="text-sm font-medium tabular-nums">
                                                    {money.format(stagedSum)}
                                                </div>
                                            </div>
                                        </div>

                                        {#if hasUnsavedChanges}
                                            <div
                                                class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                                            >
                                                You have amounts that are not saved yet.
                                                Choose <span class="font-medium"
                                                    >Save amounts</span
                                                > first so they are included.
                                            </div>
                                        {/if}

                                        <div
                                            class="max-h-72 overflow-auto rounded-lg border border-border/70"
                                        >
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>Loan #</TableHead>
                                                        <TableHead>Borrower</TableHead>
                                                        <TableHead>Method</TableHead>
                                                        <TableHead class="text-right">
                                                            Amount
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {#each staged as payment (payment.id)}
                                                        <TableRow>
                                                            <TableCell
                                                                class="font-mono text-xs"
                                                            >
                                                                {payment.loan_number}
                                                            </TableCell>
                                                            <TableCell>
                                                                {payment.borrower_name}
                                                            </TableCell>
                                                            <TableCell class="capitalize">
                                                                {payment.payment_method}
                                                            </TableCell>
                                                            <TableCell
                                                                class="text-right tabular-nums"
                                                            >
                                                                {payment.amount}
                                                            </TableCell>
                                                        </TableRow>
                                                    {:else}
                                                        <TableRow>
                                                            <TableCell
                                                                colspan={4}
                                                                class="h-20 text-center text-muted-foreground"
                                                            >
                                                                No amounts have been saved for
                                                                this day yet.
                                                            </TableCell>
                                                        </TableRow>
                                                    {/each}
                                                </TableBody>
                                            </Table>
                                        </div>

                                        <DialogFooter class="gap-2">
                                            <DialogClose>
                                                <Button variant="secondary" type="button">
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                onclick={postDay}
                                                disabled={posting ||
                                                    hasUnsavedChanges ||
                                                    staged.length === 0}
                                            >
                                                Post {money.format(stagedSum)}
                                            </Button>
                                        </DialogFooter>
                                    </div>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </div>
                {/if}

                {#if payments.length > 0}
                    <div class="grid gap-2">
                        <h3 class="text-sm font-medium">Staged payments</h3>
                        <div class="rounded-lg border border-border/70">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Payment #</TableHead>
                                        <TableHead>Loan #</TableHead>
                                        <TableHead class="text-right">Amount</TableHead>
                                        <TableHead class="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {#each payments as payment (payment.id)}
                                        <TableRow>
                                            <TableCell class="font-mono text-xs">
                                                {payment.payment_number}
                                            </TableCell>
                                            <TableCell class="font-mono text-xs">
                                                {payment.loan_number}
                                            </TableCell>
                                            <TableCell class="text-right tabular-nums">
                                                {payment.amount}
                                            </TableCell>
                                            <TableCell class="text-right">
                                                {#if canCreate}
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onclick={() =>
                                                            removeStaged(payment.id)}
                                                    >
                                                        Remove
                                                    </Button>
                                                {/if}
                                            </TableCell>
                                        </TableRow>
                                    {/each}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                {/if}
            </CardContent>
        </Card>
    {/if}
</div>
