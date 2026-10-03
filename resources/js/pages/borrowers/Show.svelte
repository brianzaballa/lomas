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
    import { Link, page } from '@inertiajs/svelte';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Phone from '@lucide/svelte/icons/phone';
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

    type BorrowerProfile = {
        id: number;
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
    };

    let { borrower }: { borrower: BorrowerProfile } = $props();

    const permissions = $derived(
        (page.props.auth?.user?.permission_names ?? []) as string[],
    );
    const canUpdate = $derived(permissions.includes('borrowers.update'));
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
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow>
                        <TableCell
                            colspan={4}
                            class="h-20 text-center text-muted-foreground"
                        >
                            This borrower has no loans yet.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <p class="flex items-center gap-2 text-xs text-muted-foreground">
                <Phone class="size-3.5" />
                Loan history appears here once a loan is released.
            </p>
        </CardContent>
    </Card>
</div>