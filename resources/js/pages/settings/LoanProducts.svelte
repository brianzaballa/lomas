<script module lang="ts">
    import { index as loanProductsIndex } from '@/routes/loan-products';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Loan products',
                href: loanProductsIndex(),
            },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import LoanProductForm from '@/components/settings/LoanProductForm.svelte';
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
    import {
        store as storeLoanProduct,
        update as updateLoanProduct,
    } from '@/routes/loan-products';
    import { toUrl } from '@/lib/utils';
    import Plus from '@lucide/svelte/icons/plus';
    import Pencil from '@lucide/svelte/icons/pencil';

    let {
        products = [],
        termUnits = [],
        paymentFrequencies = [],
        interestMethods = [],
    }: {
        products?: any[];
        termUnits?: { value: string; label: string }[];
        paymentFrequencies?: { value: string; label: string }[];
        interestMethods?: { value: string; label: string }[];
    } = $props();

    /** null keeps the list, 'new' opens a blank form, an id opens that row. */
    let editing = $state<number | 'new' | null>(null);

    const editingProduct = $derived(
        products.find((product) => product.id === editing) ?? null,
    );

    const rateLabels = $derived(
        Object.fromEntries(
            interestMethods.map((method) => [
                method.value,
                method.label,
            ]),
        ),
    );

    function collectionDays(product: any): string {
        if (!product.exclude_sundays && !product.exclude_saturdays) {
            return 'Every day';
        }

        if (product.exclude_sundays && product.exclude_saturdays) {
            return 'Monday to Friday';
        }

        return product.exclude_sundays
            ? 'Monday to Saturday'
            : 'Sunday, Monday to Friday';
    }
</script>

<AppHead title="Loan products" />

<h1 class="sr-only">Loan products</h1>

<div class="flex flex-col space-y-6">
    <Heading
        variant="small"
        title="Loan products"
        description="Term, collection days and interest. Loans released under a product keep its amounts and schedule, so a later change never alters them."
    />

    {#if editing === 'new'}
        <LoanProductForm
            action={toUrl(storeLoanProduct())}
            {termUnits}
            {paymentFrequencies}
            {interestMethods}
            submitLabel="Create product"
            onSuccess={() => (editing = null)}
            onCancel={() => (editing = null)}
        />
    {:else if editing !== null && editingProduct}
        <LoanProductForm
            action={toUrl(updateLoanProduct(editingProduct.id))}
            method="patch"
            product={editingProduct}
            {termUnits}
            {paymentFrequencies}
            {interestMethods}
            submitLabel="Save changes"
            onSuccess={() => (editing = null)}
            onCancel={() => (editing = null)}
        />
    {:else}
        <div class="flex justify-end">
            <Button size="sm" onclick={() => (editing = 'new')}>
                <Plus class="size-4" />
                Add product
            </Button>
        </div>

        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Product</TableHead>
                        <TableHead>Term</TableHead>
                        <TableHead>Collection days</TableHead>
                        <TableHead>Interest</TableHead>
                        <TableHead>First due</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">
                            <span class="sr-only">Actions</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {#each products as product (product.id)}
                        <TableRow>
                            <TableCell>
                                <div class="font-medium">
                                    {product.name}
                                </div>
                                <div class="font-mono text-xs text-muted-foreground">
                                    {product.code}
                                </div>
                            </TableCell>
                            <TableCell>
                                {product.term} {product.term_unit_label.toLowerCase()}
                            </TableCell>
                            <TableCell>
                                <div>{collectionDays(product)}</div>
                                <div class="text-xs text-muted-foreground">
                                    {product.payment_frequency_label}
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="tabular-nums">
                                    {Number(product.interest_rate)
                                        .toFixed(2)
                                        .replace(/\.00$/, '')}%
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {rateLabels[product.interest_method] ??
                                        product.interest_method}
                                </div>
                            </TableCell>
                            <TableCell>
                                {product.release_date_is_day_one
                                    ? 'Release date'
                                    : 'Next collection day'}
                            </TableCell>
                            <TableCell>
                                {#if product.is_active}
                                    <Badge variant="outline">Active</Badge>
                                {:else}
                                    <Badge variant="secondary" class="opacity-70">
                                        Inactive
                                    </Badge>
                                {/if}
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onclick={() =>
                                        (editing = product.id)}
                                >
                                    <Pencil class="size-4" />
                                    Edit
                                </Button>
                            </TableCell>
                        </TableRow>
                    {:else}
                        <TableRow>
                            <TableCell
                                colspan={7}
                                class="py-8 text-center text-muted-foreground"
                            >
                                No loan products yet.
                            </TableCell>
                        </TableRow>
                    {/each}
                </TableBody>
            </Table>
        </div>
    {/if}
</div>