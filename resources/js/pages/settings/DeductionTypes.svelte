<script module lang="ts">
    import { index as deductionTypesIndex } from '@/routes/deduction-types';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Deduction types',
                href: deductionTypesIndex(),
            },
        ],
    };
</script>

<script lang="ts">
    import Plus from '@lucide/svelte/icons/plus';
    import Pencil from '@lucide/svelte/icons/pencil';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import DeductionTypeForm from '@/components/settings/DeductionTypeForm.svelte';
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
    import { toUrl } from '@/lib/utils';
    import {
        store as storeDeductionType,
        update as updateDeductionType,
    } from '@/routes/deduction-types';

    let {
        types = [],
        calculationMethods = [],
        currency_symbol = '₱',
    }: {
        types?: any[];
        calculationMethods?: { value: string; label: string }[];
        currency_symbol?: string;
    } = $props();

    /** null keeps the list, 'new' opens a blank form, an id opens that row. */
    let editing = $state<number | 'new' | null>(null);

    const editingType = $derived(
        types.find((type) => type.id === editing) ?? null,
    );
</script>

<AppHead title="Deduction types" />

<h1 class="sr-only">Deduction types</h1>

<div class="flex flex-col space-y-6">
    <Heading
        variant="small"
        title="Deduction types"
        description="Deductions withheld at release. The amount charged to a loan is copied onto the loan, so a later change never alters a released loan. Interest is configured on the loan product."
    />

    {#if editing === 'new'}
        <DeductionTypeForm
            action={toUrl(storeDeductionType())}
            {calculationMethods}
            currencySymbol={currency_symbol}
            submitLabel="Create deduction type"
            onSuccess={() => (editing = null)}
            onCancel={() => (editing = null)}
        />
    {:else if editing !== null && editingType}
        <DeductionTypeForm
            action={toUrl(updateDeductionType(editingType.id))}
            method="patch"
            type={editingType}
            {calculationMethods}
            currencySymbol={currency_symbol}
            submitLabel="Save changes"
            onSuccess={() => (editing = null)}
            onCancel={() => (editing = null)}
        />
    {:else}
        <div class="flex justify-end">
            <Button size="sm" onclick={() => (editing = 'new')}>
                <Plus class="size-4" />
                Add deduction type
            </Button>
        </div>

        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Deduction</TableHead>
                        <TableHead>Calculation</TableHead>
                        <TableHead class="text-right">Value</TableHead>
                        <TableHead class="text-right">Order</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">
                            <span class="sr-only">Actions</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {#each types as type (type.id)}
                        <TableRow>
                            <TableCell>
                                <div class="font-medium">{type.name}</div>
                                <div class="font-mono text-xs text-muted-foreground">
                                    {type.code}
                                </div>
                            </TableCell>
                            <TableCell>
                                {type.calculation_method_label}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {type.fixed_amount_label ??
                                    type.rate_label}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {type.sort_order}
                            </TableCell>
                            <TableCell>
                                {#if type.is_active}
                                    <Badge variant="outline">Active</Badge>
                                {:else}
                                    <Badge
                                        variant="secondary"
                                        class="opacity-70"
                                    >
                                        Inactive
                                    </Badge>
                                {/if}
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onclick={() => (editing = type.id)}
                                >
                                    <Pencil class="size-4" />
                                    Edit
                                </Button>
                            </TableCell>
                        </TableRow>
                    {:else}
                        <TableRow>
                            <TableCell
                                colspan={6}
                                class="py-8 text-center text-muted-foreground"
                            >
                                No deduction types yet.
                            </TableCell>
                        </TableRow>
                    {/each}
                </TableBody>
            </Table>
        </div>
    {/if}
</div>