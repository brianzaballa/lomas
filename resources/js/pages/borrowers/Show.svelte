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
    import { Link, page, router } from '@inertiajs/svelte';
    import Banknote from '@lucide/svelte/icons/banknote';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Phone from '@lucide/svelte/icons/phone';
    import Send from '@lucide/svelte/icons/send';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import {
        Table,
        TableBody,
        TableCell,
        TableHead,
        TableHeader,
        TableRow,
    } from '@/components/ui/table';
    import { edit, index } from '@/routes/borrowers';
    import {
        edit as editLoan,
        release as releaseLoan,
        show as showLoan,
    } from '@/routes/loans';

    type BorrowerProfile = {
        id: string;
        borrower_number: string;
        full_name: string;
        first_name: string;
        middle_name: string | null;
        last_name: string;
        suffix: string | null;
        birth_date: string | null;
        mobile_number: string;
        address: string;
        status: string;
        status_label: string;
        created_at: string | null;
        updated_at: string | null;
        create_loan_url: string;
        loans: Array<{
            id: string;
            loan_number: string;
            status: string;
            status_label: string;
            application_date: string | null;
            release_date: string | null;
            gross_loan_amount: string;
            net_proceeds_amount: string;
            total_amount_payable: string;
        }>;
    };

    let { borrower }: { borrower: BorrowerProfile } = $props();

    const permissions = $derived(
        (page.props.auth?.user?.permission_names ?? []) as string[],
    );
    const canUpdate = $derived(permissions.includes('borrowers.update'));
    const canEditLoan = $derived(permissions.includes('loans.create'));
    const canReleaseLoan = $derived(permissions.includes('loans.release'));

    function postLoan(loanId: string): void {
        router.post(releaseLoan({ loan: loanId }));
    }
</script>

{#snippet detail(label: string, value: string | null)}
    <div class="grid gap-0.5">
        <dt class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
            {label}
        </dt>
        <dd class="text-sm tabular-nums">{value ?? '—'}</dd>
    </div>
{/snippet}

<AppHead title={borrower.borrower_number} />

<h1 class="sr-only">Borrower {borrower.borrower_number}</h1>

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="grid gap-1">
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-semibold tracking-tight">
                    {borrower.full_name}
                </h2>
                <Badge variant={borrower.status === 'active' ? 'secondary' : 'outline'}>
                    {borrower.status_label}
                </Badge>
            </div>
            <p class="font-mono text-sm text-muted-foreground">
                {borrower.borrower_number}
            </p>
        </div>

        <div class="flex items-center gap-2">
            {#if canUpdate}
                <Button size="sm" asChild>
                    {#snippet children(props)}
                        <Link href={edit({ borrower: borrower.id })} class={props.class}>
                            <Pencil class="size-4" />
                            Edit
                        </Link>
                    {/snippet}
                </Button>
            {/if}
            <Button size="sm" asChild>
                {#snippet children(props)}
                    <Link href={borrower.create_loan_url} class={props.class}>
                        <Banknote class="size-4" />
                        Create loan
                    </Link>
                {/snippet}
            </Button>
            <Button variant="outline" size="sm" asChild>
                {#snippet children(props)}
                    <Link href={index()} class={props.class}>All borrowers</Link>
                {/snippet}
            </Button>
        </div>
    </div>

    <Card>
        <CardHeader>
            <CardTitle>Details</CardTitle>
        </CardHeader>
        <CardContent>
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {@render detail('First name', borrower.first_name)}
                {@render detail('Middle name', borrower.middle_name)}
                {@render detail('Last name', borrower.last_name)}
                {@render detail('Suffix', borrower.suffix)}
                {@render detail('Birth date', borrower.birth_date)}
                {@render detail('Mobile number', borrower.mobile_number)}
                {@render detail('Address', borrower.address)}
                {@render detail('Registered', borrower.created_at)}
                {@render detail('Last updated', borrower.updated_at)}
            </dl>
        </CardContent>
    </Card>

    <Card>
        <CardHeader>
            <CardTitle>Loans</CardTitle>
        </CardHeader>
        <CardContent class="grid gap-3">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Loan #</TableHead>
                        <TableHead>Released</TableHead>
                        <TableHead class="text-right">Gross amount</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {#each borrower.loans as loan (loan.id)}
                        <TableRow>
                            <TableCell class="font-mono text-sm">
                                <Link
                                    href={showLoan({ loan: loan.id })}
                                    class="hover:underline"
                                >
                                    {loan.loan_number}
                                </Link>
                            </TableCell>
                            <TableCell>{loan.release_date ?? loan.application_date ?? '—'}</TableCell>
                            <TableCell class="text-right tabular-nums">{loan.gross_loan_amount}</TableCell>
                            <TableCell>
                                <Badge variant={loan.status === 'released' ? 'secondary' : 'outline'}>
                                    {loan.status_label}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    {#if loan.status === 'draft' && canEditLoan}
                                        <Button variant="outline" size="sm" asChild>
                                            {#snippet children(props)}
                                                <Link
                                                    href={editLoan({ loan: loan.id })}
                                                    class={props.class}
                                                >
                                                    <Pencil class="size-3.5" />
                                                    Edit
                                                </Link>
                                            {/snippet}
                                        </Button>
                                    {/if}
                                    {#if loan.status === 'pending_approval' && canReleaseLoan}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onclick={() => postLoan(loan.id)}
                                        >
                                            <Send class="size-3.5" />
                                            Release
                                        </Button>
                                    {/if}
                                </div>
                            </TableCell>
                        </TableRow>
                    {:else}
                        <TableRow>
                            <TableCell
                                colspan={5}
                                class="h-20 text-center text-muted-foreground"
                            >
                                This borrower has no loans yet.
                            </TableCell>
                        </TableRow>
                    {/each}
                </TableBody>
            </Table>

            <p class="flex items-center gap-2 text-xs text-muted-foreground">
                <Phone class="size-3.5" />
                Drafts can be edited. A pending loan can be released, which freezes its schedule.
            </p>
        </CardContent>
    </Card>
</div>
