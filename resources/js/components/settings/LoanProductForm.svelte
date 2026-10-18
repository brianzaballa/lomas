<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
    } from '@/components/ui/select';
    import { Separator } from '@/components/ui/separator';
    import { Textarea } from '@/components/ui/textarea';

    export type LoanProductValues = {
        name?: string | null;
        code?: string | null;
        description?: string | null;
        term?: number | string | null;
        term_unit?: string | null;
        payment_frequency?: string | null;
        exclude_sundays?: boolean;
        exclude_saturdays?: boolean;
        release_date_is_day_one?: boolean;
        interest_method?: string | null;
        interest_rate?: string | null;
        deduction_type_ids?: string[];
        is_active?: boolean;
    };

    export type DeductionTypeOption = {
        id: string;
        label: string;
        method: string;
        value_label: string;
        is_active: boolean;
    };

    let {
        action,
        method = 'post',
        product = null,
        termUnits = [],
        paymentFrequencies = [],
        interestMethods = [],
        deductionTypes = [],
        submitLabel = 'Save product',
        onSuccess = () => {},
        onCancel = () => {},
    }: {
        action: string;
        method?: 'post' | 'patch';
        product?: LoanProductValues | null;
        termUnits?: { value: string; label: string }[];
        paymentFrequencies?: { value: string; label: string }[];
        interestMethods?: { value: string; label: string }[];
        deductionTypes?: DeductionTypeOption[];
        submitLabel?: string;
        onSuccess?: () => void;
        onCancel?: () => void;
    } = $props();

    // The form is remounted for each row, so the initial values are captured on purpose.
    let termUnit = $state(untrack(() => product?.term_unit ?? 'collection_days'));
    let paymentFrequency = $state(
        untrack(() => product?.payment_frequency ?? 'daily'),
    );
    let interestMethod = $state(
        untrack(
            () => product?.interest_method ?? 'percentage_of_loan_amount',
        ),
    );
    let excludeSundays = $state(untrack(() => product?.exclude_sundays ?? true));
    let excludeSaturdays = $state(
        untrack(() => product?.exclude_saturdays ?? false),
    );
    let releaseDateIsDayOne = $state(
        untrack(() => product?.release_date_is_day_one ?? false),
    );
    let isActive = $state(untrack(() => product?.is_active ?? true));
    // Checkbox groups submit in DOM order, so the order a product charges its
    // deductions in is simply the order the types are listed here.
    let selectedDeductionTypes = $state<string[]>(
        untrack(() => product?.deduction_type_ids ?? []),
    );
</script>

<!-- An unchecked box is not submitted, and an absent flag means false. -->
<Form
    {action}
    {method}
    options={{ preserveScroll: true }}
    onSuccess={() => onSuccess()}
>
    {#snippet children({ errors, processing })}
        <div class="grid gap-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">Product name</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        autocomplete="off"
                        placeholder="100-Day Loan"
                        value={product?.name ?? ''}
                    />
                    <InputError message={errors.name} />
                </div>

                <div class="grid gap-2">
                    <Label for="code">Code</Label>
                    <Input
                        id="code"
                        name="code"
                        required
                        autocomplete="off"
                        placeholder="LOAN-100D"
                        class="font-mono uppercase"
                        value={product?.code ?? ''}
                    />
                    <InputError message={errors.code} />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="description">Description</Label>
                <Textarea
                    id="description"
                    name="description"
                    rows={2}
                    placeholder="Optional"
                    value={product?.description ?? ''}
                />
                <InputError message={errors.description} />
            </div>

            <Separator />

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="term">Term</Label>
                    <Input
                        id="term"
                        name="term"
                        type="number"
                        min="1"
                        max="3650"
                        step="1"
                        required
                        inputmode="numeric"
                        value={product?.term ?? 100}
                    />
                    <p class="text-xs text-muted-foreground">
                        Counted in collection days, never calendar days.
                    </p>
                    <InputError message={errors.term} />
                </div>

                <div class="grid gap-2">
                    <Label for="term_unit">Term unit</Label>
                    <input type="hidden" name="term_unit" value={termUnit} />
                    <Select bind:value={termUnit}>
                        <SelectTrigger id="term_unit" class="w-full">
                            {termUnits.find((o) => o.value === termUnit)
                                ?.label ?? termUnit}
                        </SelectTrigger>
                        <SelectContent>
                            {#each termUnits as option (option.value)}
                                <SelectItem value={option.value}>
                                    {option.label}
                                </SelectItem>
                            {/each}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.term_unit} />
                </div>

                <div class="grid gap-2">
                    <Label for="payment_frequency">Payment frequency</Label
                    >
                    <input
                        type="hidden"
                        name="payment_frequency"
                        value={paymentFrequency}
                    />
                    <Select bind:value={paymentFrequency}>
                        <SelectTrigger
                            id="payment_frequency"
                            class="w-full"
                        >
                            {paymentFrequencies.find(
                                (o) => o.value === paymentFrequency,
                            )?.label ?? paymentFrequency}
                        </SelectTrigger>
                        <SelectContent>
                            {#each paymentFrequencies as option (option.value)}
                                <SelectItem value={option.value}>
                                    {option.label}
                                </SelectItem>
                            {/each}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.payment_frequency} />
                </div>
            </div>

            <Separator />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="interest_method">Interest method</Label>
                    <input
                        type="hidden"
                        name="interest_method"
                        value={interestMethod}
                    />
                    <Select bind:value={interestMethod}>
                        <SelectTrigger id="interest_method" class="w-full">
                            {interestMethods.find(
                                (o) => o.value === interestMethod,
                            )?.label ?? interestMethod}
                        </SelectTrigger>
                        <SelectContent>
                            {#each interestMethods as option (option.value)}
                                <SelectItem value={option.value}>
                                    {option.label}
                                </SelectItem>
                            {/each}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.interest_method} />
                </div>

                <div class="grid gap-2">
                    <Label for="interest_rate">Interest rate</Label>
                    <div class="relative">
                        <Input
                            id="interest_rate"
                            name="interest_rate"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            required
                            inputmode="decimal"
                            class="pr-8"
                            value={product?.interest_rate ?? '10.00'}
                        />
                        <span
                            class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                        >
                            %
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Charged on the original loan amount and withheld at
                        release, so the schedule collects the principal only.
                    </p>
                    <InputError message={errors.interest_rate} />
                </div>
            </div>

            <Separator />

            <fieldset class="grid gap-3">
                <legend class="text-sm font-medium">
                    Collection days
                </legend>

                <label class="flex items-start gap-2 text-sm">
                    <input
                        id="exclude_sundays"
                        type="checkbox"
                        name="exclude_sundays"
                        value="1"
                        bind:checked={excludeSundays}
                        class="mt-0.5 size-4 rounded accent-primary"
                    />
                    <span>
                        Do not collect on Sundays
                        <span class="block text-xs text-muted-foreground">
                            Always excluded when this is on.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-2 text-sm">
                    <input
                        id="exclude_saturdays"
                        type="checkbox"
                        name="exclude_saturdays"
                        value="1"
                        bind:checked={excludeSaturdays}
                        class="mt-0.5 size-4 rounded accent-primary"
                    />
                    <span>
                        Do not collect on Saturdays
                        <span class="block text-xs text-muted-foreground">
                            Leave this off to collect Monday to Saturday.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-2 text-sm">
                    <input
                        id="release_date_is_day_one"
                        type="checkbox"
                        name="release_date_is_day_one"
                        value="1"
                        bind:checked={releaseDateIsDayOne}
                        class="mt-0.5 size-4 rounded accent-primary"
                    />
                    <span>
                        Count the release date as the first collection
                        <span class="block text-xs text-muted-foreground">
                            When off, the first collection is the next
                            collection day after the release date.
                        </span>
                    </span>
                </label>

                <InputError message={errors.exclude_sundays} />
                <InputError message={errors.exclude_saturdays} />
                <InputError message={errors.release_date_is_day_one} />
            </fieldset>

            <Separator />

            <fieldset class="grid gap-3">
                <legend class="text-sm font-medium">Deductions</legend>
                <p class="text-xs text-muted-foreground">
                    The deductions this product charges on every loan. Interest
                    is not listed here because its rate is set above. A loan
                    keeps its own copy of the deductions, so changing this list
                    never changes a loan that was already encoded.
                </p>

                {#each deductionTypes as type (type.id)}
                    <label class="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            name="deduction_type_ids[]"
                            value={type.id}
                            bind:group={selectedDeductionTypes}
                            class="mt-0.5 size-4 rounded accent-primary"
                        />
                        <span>
                            {type.label}
                            <span
                                class="block text-xs text-muted-foreground"
                            >
                                {type.method} &middot; {type.value_label}
                                {#if !type.is_active}
                                    &middot; inactive, so not charged on new
                                    loans
                                {/if}
                            </span>
                        </span>
                    </label>
                {:else}
                    <p class="text-sm text-muted-foreground">
                        No deduction types configured yet.
                    </p>
                {/each}

                <InputError message={errors.deduction_type_ids} />
            </fieldset>

            <Separator />

            <label class="flex items-start gap-2 text-sm">
                <input
                    id="is_active"
                    type="checkbox"
                    name="is_active"
                    value="1"
                    bind:checked={isActive}
                    class="mt-0.5 size-4 rounded accent-primary"
                />
                <span>
                    Active
                    <span class="block text-xs text-muted-foreground">
                        An inactive product stays available for existing loans
                        but is not offered when a loan is encoded.
                    </span>
                </span>
            </label>

            <div class="flex items-center gap-2">
                <Button type="submit" disabled={processing}>
                    {submitLabel}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onclick={() => onCancel()}
                >
                    Cancel
                </Button>
            </div>
        </div>
    {/snippet}
</Form>