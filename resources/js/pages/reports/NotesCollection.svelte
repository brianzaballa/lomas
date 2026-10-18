<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Reports',
                href: '/reports/notes-collection',
            },
            {
                title: 'Notes Collection Report',
                href: '/reports/notes-collection',
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
        Table,
        TableBody,
        TableCell,
        TableFooter,
        TableHead,
        TableHeader,
        TableRow,
    } from '@/components/ui/table';
    import { notesCollection } from '@/routes/reports';
    import { exportMethod, post, unpost } from '@/routes/reports/notes-collection';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import Download from '@lucide/svelte/icons/download';
    import FileSpreadsheet from '@lucide/svelte/icons/file-spreadsheet';
    import Printer from '@lucide/svelte/icons/printer';

    type DayHeader = {
        date: string;
        weekday: string;
        label: string;
        short: string;
    };

    type MemberRow = {
        borrower_number: string;
        borrower_name: string;
        loan_number: string;
        days: Record<string, string>;
        total_collected: string;
        overdue: string;
        is_overdue: boolean;
        collectibles: string | null;
        has_collectibles: boolean;
    };

    type AreaGroup = {
        area: string;
        count: number;
        subtotal_collected: string;
        subtotal_overdue: string;
        has_overdue: boolean;
        subtotal_collectibles: string | null;
        has_collectibles: boolean;
        members: MemberRow[];
    };

    type Grand = {
        count: number;
        total_collected: string;
        total_overdue: string;
        has_overdue: boolean;
        total_collectibles: string | null;
        has_collectibles: boolean;
    };

    type Period = {
        start: string;
        end: string;
        label: string;
        mode: string;
        is_current: boolean;
    };

    type Posting = {
        status: string | null;
        posted_at: string | null;
    };

    type Report = {
        week_start: string;
        week_end: string;
        label: string;
        previous_week: string;
        next_week: string;
        period: Period;
        posting: Posting;
        day_headers: DayHeader[];
        areas: AreaGroup[];
        grand: Grand;
        total: string;
        total_count: number;
    };

    let { report, week }: { report: Report; week: string } = $props();

    const permissions = $derived(
        (page.props.auth?.user?.permission_names ?? []) as string[],
    );
    const canExport = $derived(permissions.includes('reports.export'));
    const canPost = $derived(permissions.includes('reports.post'));

    const isPosted = $derived(report.posting?.status === 'posted');
    const isCurrent = $derived(report.period?.is_current);

    let posting = $state(false);

    function go(value: string): void {
        router.get(notesCollection({ query: { period: value } }), {
            preserveState: false,
        });
    }

    function exportUrl(query: Record<string, string | number>): string {
        return exportMethod({ query: { period: report.week_start, ...query } }).url;
    }

    function postPeriod(): void {
        router.post(
            post({ query: { period: report.week_start } }).url,
            {},
            {
                preserveScroll: true,
                onStart: () => (posting = true),
                onFinish: () => (posting = false),
            },
        );
    }

    function unpostPeriod(): void {
        router.post(
            unpost({ query: { period: report.week_start } }).url,
            {},
            {
                preserveScroll: true,
                onStart: () => (posting = true),
                onFinish: () => (posting = false),
            },
        );
    }
</script>

<AppHead title="Notes Collection Report" />

<h1 class="sr-only">Notes Collection Report</h1>

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <Heading variant="small" title="Notes Collection Report" description={`Period ${report.label}`} />
            <p class="text-sm text-muted-foreground">
                {#if isCurrent}
                    One row per member by area. Overdue is what was unpaid before the period started; collectibles is what is still due inside it.
                {:else}
                    One row per member by area. Overdue is everything still unpaid at the end of the period.
                {/if}
            </p>

            {#if isPosted}
                <Badge variant="outline" class="mt-2 border-green-700 text-green-700">
                    Posted {report.posting.posted_at}
                </Badge>
            {/if}
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button variant="outline" size="icon" onclick={() => go(report.previous_week)} aria-label="Previous period">
                <ChevronLeft class="size-4" />
            </Button>
            <Input
                type="date"
                value={week}
                class="w-44"
                onchange={(event) => go(event.currentTarget.value)}
                aria-label="Select period"
            />
            <Button variant="outline" size="icon" onclick={() => go(report.next_week)} aria-label="Next period">
                <ChevronRight class="size-4" />
            </Button>

            {#if canPost}
                {#if isPosted}
                    <Dialog>
                        <DialogTrigger>
                            <Button variant="outline" disabled={posting}>Unpost</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Unpost the period {report.label}</DialogTitle>
                            <DialogDescription>
                                Unposting reopens this period so its payments can be edited or
                                reversed again. The report figure stays recalculated from the
                                transactions.
                            </DialogDescription>
                            <DialogFooter class="gap-2">
                                <DialogClose>
                                    <Button variant="secondary" type="button">Cancel</Button>
                                </DialogClose>
                                <Button variant="destructive" onclick={unpostPeriod} disabled={posting}>
                                    Unpost period
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                {:else}
                    <Dialog>
                        <DialogTrigger>
                            <Button disabled={posting}>Post period</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Post the period {report.label}</DialogTitle>
                            <DialogDescription>
                                Posting signs off this period and locks the payments dated inside
                                it. They cannot be staged, edited or reversed until the period is
                                unposted.
                            </DialogDescription>
                            <DialogFooter class="gap-2">
                                <DialogClose>
                                    <Button variant="secondary" type="button">Cancel</Button>
                                </DialogClose>
                                <Button onclick={postPeriod} disabled={posting}>Post period</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                {/if}
            {/if}

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
            <CardHeader><CardTitle>Members</CardTitle></CardHeader>
            <CardContent class="text-2xl font-semibold tabular-nums">{report.grand.count}</CardContent>
        </Card>
        <Card>
            <CardHeader><CardTitle>Collected</CardTitle></CardHeader>
            <CardContent class="text-2xl font-semibold tabular-nums">{report.grand.total_collected}</CardContent>
        </Card>
        <Card>
            <CardHeader><CardTitle>Overdue</CardTitle></CardHeader>
            <CardContent class="text-2xl font-semibold tabular-nums {report.grand.has_overdue ? 'text-destructive' : ''}">
                {report.grand.total_overdue}
            </CardContent>
        </Card>
    </div>

    {#if isCurrent}
        <div class="grid gap-3 sm:grid-cols-2">
            <Card>
                <CardHeader><CardTitle>Collectibles this period</CardTitle></CardHeader>
                <CardContent class="text-2xl font-semibold tabular-nums {report.grand.has_collectibles ? 'text-amber-600' : ''}">
                    {report.grand.total_collectibles ?? '—'}
                </CardContent>
            </Card>
        </div>
    {/if}

    <Card>
        <CardHeader>
            <CardTitle>Ledger</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="overflow-x-auto rounded-lg border border-border/70">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="min-w-48">Member</TableHead>
                            {#each report.day_headers as day (day.date)}
                                <TableHead class="whitespace-nowrap text-right">
                                    <span class="block">{day.short}</span>
                                    <span class="block text-xs font-normal text-muted-foreground">{day.label}</span>
                                </TableHead>
                            {/each}
                            <TableHead class="text-right">Total</TableHead>
                            {#if isCurrent}
                                <TableHead class="text-right">Collectible</TableHead>
                            {/if}
                            <TableHead class="text-right">Overdue</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {#each report.areas as area (area.area)}
                            <TableRow class="bg-muted/50">
                                <TableCell colspan={report.day_headers.length + (isCurrent ? 4 : 3)} class="text-xs font-semibold uppercase tracking-wide">
                                    {area.area} · {area.count} {area.count === 1 ? 'member' : 'members'}
                                </TableCell>
                            </TableRow>
                            {#each area.members as member (member.borrower_number + member.loan_number)}
                                <TableRow>
                                    <TableCell>
                                        <div class="font-medium">{member.borrower_name}</div>
                                        <div class="text-xs text-muted-foreground">
                                            {member.borrower_number} · {member.loan_number}
                                        </div>
                                    </TableCell>
                                    {#each report.day_headers as day (day.date)}
                                        <TableCell class="text-right tabular-nums">{member.days[day.date] || '—'}</TableCell>
                                    {/each}
                                    <TableCell class="text-right font-medium tabular-nums">{member.total_collected}</TableCell>
                                    {#if isCurrent}
                                        <TableCell class="text-right tabular-nums {member.has_collectibles ? 'font-medium text-amber-600' : 'text-muted-foreground'}">
                                            {member.collectibles ?? '—'}
                                        </TableCell>
                                    {/if}
                                    <TableCell class="text-right tabular-nums {member.is_overdue ? 'font-medium text-destructive' : 'text-muted-foreground'}">
                                        {member.overdue}
                                    </TableCell>
                                </TableRow>
                            {/each}
                            <TableRow class="bg-muted/30 font-medium">
                                <TableCell>{area.area} subtotal</TableCell>
                                <TableCell colspan={report.day_headers.length}></TableCell>
                                <TableCell class="text-right tabular-nums">{area.subtotal_collected}</TableCell>
                                {#if isCurrent}
                                    <TableCell class="text-right tabular-nums {area.has_collectibles ? 'text-amber-600' : 'text-muted-foreground'}">
                                        {area.subtotal_collectibles ?? '—'}
                                    </TableCell>
                                {/if}
                                <TableCell class="text-right tabular-nums {area.has_overdue ? 'text-destructive' : 'text-muted-foreground'}">
                                    {area.subtotal_overdue}
                                </TableCell>
                            </TableRow>
                        {:else}
                            <TableRow>
                                <TableCell colspan={report.day_headers.length + (isCurrent ? 4 : 3)} class="h-24 text-center text-muted-foreground">
                                    No members were expected to pay in this period.
                                </TableCell>
                            </TableRow>
                        {/each}
                    </TableBody>
                    <TableFooter>
                        <TableRow>
                            <TableCell class="font-medium">Grand total</TableCell>
                            <TableCell colspan={report.day_headers.length}></TableCell>
                            <TableCell class="text-right font-semibold tabular-nums">{report.grand.total_collected}</TableCell>
                            {#if isCurrent}
                                <TableCell class="text-right font-semibold tabular-nums {report.grand.has_collectibles ? 'text-amber-600' : 'text-muted-foreground'}">
                                    {report.grand.total_collectibles ?? '—'}
                                </TableCell>
                            {/if}
                            <TableCell class="text-right font-semibold tabular-nums {report.grand.has_overdue ? 'text-destructive' : 'text-muted-foreground'}">
                                {report.grand.total_overdue}
                            </TableCell>
                        </TableRow>
                    </TableFooter>
                </Table>
            </div>
        </CardContent>
    </Card>
</div>
