<script module lang="ts">
    import { create as createLoan } from '@/routes/loans';

    export const layout = {
        breadcrumbs: [
            { title: 'Loans', href: createLoan() },
            { title: 'New Loan', href: createLoan() },
        ],
    };
</script>

<script lang="ts">
    import { router, page } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import Banknote from '@lucide/svelte/icons/banknote';
    import Calculator from '@lucide/svelte/icons/calculator';
    import Plus from '@lucide/svelte/icons/plus';
    import Search from '@lucide/svelte/icons/search';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import SearchInput from '@/components/SearchInput.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Select, SelectContent, SelectItem, SelectTrigger } from '@/components/ui/select';
    import { Separator } from '@/components/ui/separator';
    import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
    import { index as borrowersIndex } from '@/routes/borrowers';
    import { create as loanCreate, preview as loanPreview, store as loanStore } from '@/routes/loans';

    type BorrowerRow = {
        id: string;
        borrower_number: string;
        full_name: string;
        mobile_number: string;
        default_area_id?: string | null;
    };

    type Product = {
        id: string;
        name: string;
        code: string;
        term_label: string;
        interest_rate_label: string;
    };

    type PreviewDeduction = {
        deduction_type_id: string | null;
        code: string | null;
        name: string;
        calculation_method: string;
        rate: string | null;
        fixed_amount: string | null;
        amount: string;
        is_interest: boolean;
        is_applied: boolean;
        is_custom: boolean;
        is_toggleable: boolean;
        sort_order: number;
    };

    type Preview = {
        gross_loan_amount: string;
        interest_amount: string;
        other_deductions_amount: string;
        deductions_amount: string;
        net_proceeds_amount: string;
        total_amount_payable: string;
        daily_amortization: string;
        collection_days: number;
        deductions?: PreviewDeduction[];
    };

    type CustomDeduction = { name: string; amount: string };

    const MAX_CUSTOM_DEDUCTIONS = 10;

    let {
        borrowers = [],
        products = [],
        areas = [],
        today,
        search = '',
        selectedBorrowerId = null,
        selectedProductId = null,
        defaultAreaId: initialDefaultAreaId = null,
        grossLoanAmount: initialGrossLoanAmount = '10000.00',
        interestOverrideAmount: initialInterestOverrideAmount = '',
        excludedDeductionCodes: initialExcludedCodes = [],
        customDeductions: initialCustomDeductions = [],
        preview = null,
    }: {
        borrowers?: BorrowerRow[];
        products?: Product[];
        areas?: Array<{ id: string; code: string; name: string }>;
        today: string;
        search?: string;
        selectedBorrowerId?: string | null;
        selectedProductId?: string | null;
        defaultAreaId?: string | null;
        grossLoanAmount?: string;
        interestOverrideAmount?: string;
        excludedDeductionCodes?: string[];
        customDeductions?: CustomDeduction[];
        preview?: Preview | null;
    } = $props();

    // Every field below starts from the server rendered value and is then owned
    // by the encoder, so the initial value is captured once on purpose.
    let searchValue = $state(untrack(() => search));
    let selectedBorrower = $state<string>(untrack(() => selectedBorrowerId ?? ''));
    let areaId = $state<string>(untrack(() => initialDefaultAreaId ?? ''));
    let selectedProduct = $state<string>(
        untrack(() => selectedProductId ?? products[0]?.id ?? ''),
    );
    let amountValue = $state(untrack(() => initialGrossLoanAmount));
    let interestOverride = $state(untrack(() => initialInterestOverrideAmount));
    // The product sets the deduction list; these two are what the encoder
    // changed on top of it for this loan only.
    let excludedCodes = $state<string[]>(
        untrack(() => [...(initialExcludedCodes ?? [])]),
    );
    let customDeductions = $state<CustomDeduction[]>(
        untrack(() =>
            (initialCustomDeductions ?? []).map((deduction) => ({
                ...deduction,
            })),
        ),
    );

    let livePreview = $state<Preview | null>(null);
    let syncedPreview = $state<Preview | null>(null);
    let previewPending = $state(false);
    let previewFailed = $state(false);

    // Control flow only, never rendered, so these stay non-reactive.
    let recalculateTimer: ReturnType<typeof setTimeout> | undefined;
    let recalculateController: AbortController | undefined;

    const RECALCULATE_DELAY_MS = 250;

    const borrowerRows = $derived(borrowers ?? []);
    const selectedBorrowerRow = $derived(
        borrowerRows.find((borrower) => borrower.id === selectedBorrower) ?? null,
    );
    const selectedProductLabel = $derived(
        (products ?? []).find((product) => product.id === selectedProduct) ?? null,
    );
    const previewDeductions = $derived(livePreview?.deductions ?? []);
    const toggleableDeductions = $derived(
        previewDeductions.filter(
            (deduction) => deduction.is_toggleable && !deduction.is_interest,
        ),
    );
    // A blank row is not a charge. It is only sent once it has a name and an
    // amount, which is also how the server reads the selection.
    const usableCustomDeductions = $derived(
        customDeductions
            .filter(
                (deduction) =>
                    deduction.name.trim() !== '' &&
                    deduction.amount.trim() !== '' &&
                    !Number.isNaN(Number(deduction.amount)),
            )
            .map((deduction) => ({
                name: deduction.name.trim(),
                amount: deduction.amount.trim(),
            })),
    );
    const canAddCustomDeduction = $derived(
        customDeductions.length < MAX_CUSTOM_DEDUCTIONS,
    );
    const permissions = $derived((page.props.auth?.user?.permission_names ?? []) as string[]);
    const devMode = $derived(Boolean(page.props.devMode));
    const isAdministrator = $derived((page.props.auth?.user?.role_names ?? []).includes('Administrator'));
    const canCreateLoan = $derived(devMode ? isAdministrator || true : permissions.includes('loans.create'));
    const canSave = $derived(Boolean(selectedBorrower && selectedProduct && amountValue));

    // A page visit also returns a fresh preview, so adopt it when it arrives.
    $effect(() => {
        if (preview && preview !== syncedPreview) {
            syncedPreview = preview;
            livePreview = preview;
        }
    });

    // A page visit after selecting a borrower carries the borrower's default
    // area, so adopt it as the area for this loan until the encoder overrides.
    $effect(() => {
        if (initialDefaultAreaId && areaId === '') {
            areaId = initialDefaultAreaId;
        }
    });

    $effect(() => {
        const borrower = borrowers?.find((b) => b.id === selectedBorrower);
        if (borrower) {
            const br: any = borrower as any;
            if (br.default_area_id && areaId === (initialDefaultAreaId ?? '')) {
                areaId = br.default_area_id;
            } else if (br.default_area_id && !areaId) {
                areaId = br.default_area_id;
            }
        }
    });

    $effect(() => {
        return () => cancelRecalculation();
    });

    function cancelRecalculation(): void {
        if (recalculateTimer !== undefined) {
            clearTimeout(recalculateTimer);
            recalculateTimer = undefined;
        }

        recalculateController?.abort();
        recalculateController = undefined;
    }

    async function recalculate(): Promise<void> {
        cancelRecalculation();

        const product = selectedProduct;
        const amount = amountValue.trim();
        const override = interestOverride.trim();

        if (!product || amount === '') {
            livePreview = null;
            previewPending = false;
            previewFailed = false;
            return;
        }

        const controller = new AbortController();
        recalculateController = controller;
        previewPending = true;
        previewFailed = false;

        // Wayfinder serialises an array of objects as "[object Object]", so the
        // custom rows travel as an index keyed object instead.
        const custom = Object.fromEntries(
            usableCustomDeductions.map((deduction, index) => [
                index,
                deduction,
            ]),
        );

        try {
            const response = await fetch(
                loanPreview.url({
                    query: {
                        loan_product_id: product,
                        gross_loan_amount: amount,
                        interest_override_amount: override || undefined,
                        excluded_deduction_codes:
                            excludedCodes.length > 0 ? [...excludedCodes] : undefined,
                        custom_deductions: custom,
                    },
                }),
                { headers: { Accept: 'application/json' }, signal: controller.signal },
            );

            if (!response.ok) {
                previewFailed = true;
                return;
            }

            const result = (await response.json()) as Preview;

            if (controller.signal.aborted) return;

            livePreview = result;
        } catch {
            previewFailed = !controller.signal.aborted;
        } finally {
            if (recalculateController === controller) {
                recalculateController = undefined;
                previewPending = false;
            }
        }
    }

    function scheduleRecalculate(): void {
        if (recalculateTimer !== undefined) clearTimeout(recalculateTimer);

        recalculateTimer = setTimeout(() => {
            recalculateTimer = undefined;
            void recalculate();
        }, RECALCULATE_DELAY_MS);
    }

    function toggleDeduction(code: string): void {
        excludedCodes = excludedCodes.includes(code)
            ? excludedCodes.filter((excluded) => excluded !== code)
            : [...excludedCodes, code];

        // A checkbox is a deliberate action, so it is not debounced: the row
        // reflects the server's answer rather than an optimistic guess.
        void recalculate();
    }

    function addCustomDeduction(): void {
        if (!canAddCustomDeduction) return;

        customDeductions = [...customDeductions, { name: '', amount: '' }];
    }

    /**
     * How a deduction works, in the words the product settings use: a rate on
     * the loan amount, or a fixed amount.
     */
    function deductionBasis(deduction: PreviewDeduction): string {
        if (deduction.calculation_method === 'fixed_amount') {
            return deduction.fixed_amount ?? '';
        }

        return deduction.rate === null ? '' : `${deduction.rate}% of loan amount`;
    }

    function removeCustomDeduction(index: number): void {
        customDeductions = customDeductions.filter((_, position) => position !== index);
        scheduleRecalculate();
    }

    function updateCustomDeduction(
        index: number,
        field: 'name' | 'amount',
        value: string,
    ): void {
        customDeductions = customDeductions.map((deduction, position) =>
            position === index ? { ...deduction, [field]: value } : deduction,
        );
    }

    function syncSearch(): void {
        router.get(loanCreate({
            query: {
                search: searchValue || undefined,
                borrower: selectedBorrower || undefined,
                product: selectedProduct || undefined,
                amount: amountValue || undefined,
                interest_override_amount: interestOverride || undefined,
                excluded_deduction_codes:
                    excludedCodes.length > 0 ? [...excludedCodes] : undefined,
                custom_deductions: Object.fromEntries(
                    usableCustomDeductions.map((deduction, index) => [
                        index,
                        deduction,
                    ]),
                ),
            },
        }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function selectBorrower(borrowerId: string): void {
        selectedBorrower = borrowerId;

        const borrower = borrowerRows.find((row) => row.id === borrowerId);

        if (borrower?.default_area_id) {
            areaId = borrower.default_area_id;
        }

        syncSearch();
    }

    function selectProduct(productId: string): void {
        selectedProduct = productId;
        syncSearch();
        void recalculate();
    }

    function submitDraft(event: SubmitEvent): void {
        event.preventDefault();

        router.post(loanStore().url, {
            borrower_id: selectedBorrower,
            loan_product_id: selectedProduct,
            area_id: areaId || undefined,
            gross_loan_amount: amountValue,
            interest_override_amount: interestOverride || undefined,
            excluded_deduction_codes:
                excludedCodes.length > 0 ? [...excludedCodes] : undefined,
            custom_deductions:
                usableCustomDeductions.length > 0
                    ? usableCustomDeductions
                    : undefined,
            application_date: today,
        }, {
            preserveScroll: true,
        });
    }
</script>

<AppHead title="New Loan" />

<h1 class="sr-only">New Loan</h1>

<div class="grid gap-6 p-4 xl:grid-cols-[1.4fr_1fr]">
    <div class="space-y-6">
        <Heading variant="small" title="New Loan" description="Search a borrower, choose a product, enter the amount and review the preview before saving." />

        <Card>
            <CardHeader>
                <CardTitle>Borrower search</CardTitle>
                <CardDescription>Search by borrower number, name or mobile number.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <SearchInput bind:value={searchValue} name="search" placeholder="Search borrowers" ariaLabel="Search borrowers" />
                <Button type="button" size="sm" variant="outline" onclick={syncSearch}>
                    <Search class="size-4" />
                    Search
                </Button>

                <div class="rounded-lg border border-dashed border-border/70 px-3 py-2 text-sm space-y-2">
                    <div>
                        <span class="text-muted-foreground">Selected borrower:</span>
                        <span class="ml-2 font-medium">{selectedBorrowerRow?.full_name ?? 'None selected'}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground">Area:</span>
                        <input type="hidden" name="area_id" value={areaId} />
                        <Select bind:value={areaId}>
                            <SelectTrigger class="h-8 w-56">
                                {areas.find((option) => option.id === areaId)?.name ?? 'Select area'}
                            </SelectTrigger>
                            <SelectContent>
                                {#each areas as area (area.id)}
                                    <SelectItem value={area.id}>{area.name}</SelectItem>
                                {/each}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="rounded-lg border border-border/70">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Borrower #</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Mobile</TableHead>
                                <TableHead class="text-right">Select</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {#each borrowerRows as borrower (borrower.id)}
                                <TableRow class={selectedBorrower === borrower.id ? 'bg-muted/40' : ''}>
                                    <TableCell class="font-mono text-xs">{borrower.borrower_number}</TableCell>
                                    <TableCell>{borrower.full_name}</TableCell>
                                    <TableCell class="font-mono text-xs">{borrower.mobile_number}</TableCell>
                                    <TableCell class="text-right">
                                        <Button type="button" size="sm" variant={selectedBorrower === borrower.id ? 'secondary' : 'outline'} onclick={() => selectBorrower(borrower.id)}>
                                            {selectedBorrower === borrower.id ? 'Selected' : 'Select'}
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            {:else}
                                <TableRow>
                                    <TableCell colspan={4} class="h-24 text-center text-muted-foreground">
                                        No borrowers found.
                                    </TableCell>
                                </TableRow>
                            {/each}
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    </div>

    <div class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle>Loan encoding</CardTitle>
                <CardDescription>Enter the gross amount and preview the deduction snapshot.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-2">
                    <Label for="product">Loan product</Label>
                    <Select value={selectedProduct} onValueChange={selectProduct}>
                        <SelectTrigger id="product">{selectedProductLabel?.name ?? 'Select a product'}</SelectTrigger>
                        <SelectContent>
                            {#each products as product (product.id)}
                                <SelectItem value={product.id}>{product.name} · {product.term_label} · {product.interest_rate_label}</SelectItem>
                            {/each}
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-2">
                    <Label for="gross_loan_amount">Gross loan amount</Label>
                    <Input id="gross_loan_amount" bind:value={amountValue} inputmode="decimal" placeholder="10000.00" oninput={scheduleRecalculate} />
                </div>

                <div class="grid gap-2">
                    <Label for="interest_override_amount">Interest override amount</Label>
                    <Input id="interest_override_amount" bind:value={interestOverride} inputmode="decimal" placeholder="Leave blank to use product rate" oninput={scheduleRecalculate} />
                </div>

                <div class="grid gap-2">
                    <Label for="application_date">Application date</Label>
                    <Input id="application_date" value={today} readonly />
                </div>

                <div class="flex gap-2">
                    <Button type="button" variant="secondary" disabled={previewPending} onclick={() => void recalculate()}>
                        <Calculator class="size-4" />
                        Recalculate
                    </Button>
                    <Button type="button" variant="outline" onclick={() => router.get(borrowersIndex())}>
                        <Search class="size-4" />
                        Back to borrowers
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Save</CardTitle>
                <CardDescription>The server will recalculate and store the draft values.</CardDescription>
            </CardHeader>
            <CardContent>
                <form method="post" action={loanStore().url} class="flex items-center gap-2" onsubmit={submitDraft}>
                    <input type="hidden" name="borrower_id" value={selectedBorrower} />
                    <input type="hidden" name="loan_product_id" value={selectedProduct} />
                    <input type="hidden" name="gross_loan_amount" value={amountValue} />
                    <input type="hidden" name="interest_override_amount" value={interestOverride} />
                    {#each excludedCodes as code (code)}
                        <input type="hidden" name="excluded_deduction_codes[]" value={code} />
                    {/each}
                    {#each usableCustomDeductions as deduction, index (index)}
                        <input type="hidden" name={`custom_deductions[${index}][name]`} value={deduction.name} />
                        <input type="hidden" name={`custom_deductions[${index}][amount]`} value={deduction.amount} />
                    {/each}
                    <input type="hidden" name="application_date" value={today} />
                    <Button type="submit" disabled={!canSave || !canCreateLoan}>
                        <Banknote class="size-4" />
                        Save draft loan
                    </Button>
                    <Badge variant={canSave && canCreateLoan ? 'secondary' : 'outline'}>
                        {canCreateLoan ? (canSave ? 'Ready to save' : 'Select borrower and product') : 'No access'}
                    </Badge>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    Preview
                    {#if previewPending}
                        <Badge variant="secondary">Recalculating</Badge>
                    {/if}
                </CardTitle>
                <CardDescription>The server recalculates these values before persistence.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3 text-sm">
                {#if previewFailed}
                    <p class="text-destructive">
                        The preview could not be recalculated. Check the amount and the product.
                    </p>
                {/if}
                <div class="flex items-center justify-between gap-2">
                    <span class="text-muted-foreground">Loan amount</span>
                    <span class="font-semibold tabular-nums">{livePreview?.gross_loan_amount ?? amountValue}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-muted-foreground">Interest</span>
                    <span class="font-semibold tabular-nums">{livePreview?.interest_amount ?? '0.00'}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-muted-foreground">Other deductions</span>
                    <span class="font-semibold tabular-nums">{livePreview?.other_deductions_amount ?? '0.00'}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-muted-foreground">Total deductions</span>
                    <span class="font-semibold tabular-nums">{livePreview?.deductions_amount ?? '0.00'}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-muted-foreground">Net proceeds</span>
                    <span class="font-semibold tabular-nums">{livePreview?.net_proceeds_amount ?? '0.00'}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-muted-foreground">Total amount payable</span>
                    <span class="font-semibold tabular-nums">{livePreview?.total_amount_payable ?? amountValue}</span>
                </div>
                <Separator />
                <div class="flex items-center justify-between gap-2">
                    <span class="text-muted-foreground">Daily amortization</span>
                    <span class="font-semibold tabular-nums">{livePreview?.daily_amortization ?? '—'}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-muted-foreground">Collection days</span>
                    <span class="font-semibold tabular-nums">{livePreview?.collection_days ?? selectedProductLabel?.term_label ?? '—'}</span>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Deductions</CardTitle>
                <CardDescription>
                    The product decides what is charged. Switch a deduction off
                    for this loan, or add a charge that belongs to this loan
                    only.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="rounded-md border border-border/70">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Deduction</TableHead>
                                <TableHead>Basis</TableHead>
                                <TableHead class="text-right">Amount</TableHead>
                                <TableHead class="text-right">Charge</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {#each previewDeductions as deduction (deduction.code ?? `custom-${deduction.sort_order}`)}
                                <TableRow class={!deduction.is_applied ? 'opacity-60' : ''}>
                                    <TableCell>
                                        <div class="text-sm font-medium">
                                            {deduction.name}
                                        </div>
                                        {#if deduction.is_interest}
                                            <div class="text-xs text-muted-foreground">
                                                Set by the product rate or the override above.
                                            </div>
                                        {/if}
                                    </TableCell>
                                    <TableCell class="text-sm text-muted-foreground">
                                        {deductionBasis(deduction)}
                                    </TableCell>
                                    <TableCell class="text-right font-mono text-sm tabular-nums">
                                        {deduction.is_applied ? deduction.amount : '—'}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        {#if deduction.is_toggleable && deduction.code}
                                            <input
                                                type="checkbox"
                                                checked={deduction.is_applied}
                                                onchange={() => toggleDeduction(deduction.code as string)}
                                                class="size-4 rounded accent-primary"
                                                aria-label={`Charge ${deduction.name}`}
                                            />
                                        {:else}
                                            <span class="text-xs text-muted-foreground">Always</span>
                                        {/if}
                                    </TableCell>
                                </TableRow>
                            {:else}
                                <TableRow>
                                    <TableCell colspan={4} class="py-6 text-center text-muted-foreground">
                                        Enter an amount and a product to see the deductions.
                                    </TableCell>
                                </TableRow>
                            {/each}
                        </TableBody>
                    </Table>
                </div>

                <div class="grid gap-2">
                    <Label>Custom deductions</Label>

                    {#each customDeductions as deduction, index (index)}
                        <div class="flex items-end gap-2">
                            <div class="grid flex-1 gap-1">
                                <Input
                                    value={deduction.name}
                                    placeholder="Charge name"
                                    aria-label="Custom deduction name"
                                    oninput={(event) => updateCustomDeduction(index, 'name', event.currentTarget.value)}
                                    onchange={scheduleRecalculate}
                                />
                            </div>
                            <div class="w-32">
                                <Input
                                    value={deduction.amount}
                                    inputmode="decimal"
                                    placeholder="0.00"
                                    aria-label="Custom deduction amount"
                                    oninput={(event) => updateCustomDeduction(index, 'amount', event.currentTarget.value)}
                                    onchange={scheduleRecalculate}
                                />
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                aria-label="Remove custom deduction"
                                onclick={() => removeCustomDeduction(index)}
                            >
                                Remove
                            </Button>
                        </div>
                    {/each}

                    <div>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={!canAddCustomDeduction}
                            onclick={addCustomDeduction}
                        >
                            <Plus class="size-4" />
                            Add deduction
                        </Button>
                        {#if !canAddCustomDeduction}
                            <span class="ml-2 text-xs text-muted-foreground">
                                A loan may carry at most {MAX_CUSTOM_DEDUCTIONS} custom charges.
                            </span>
                        {/if}
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</div>
