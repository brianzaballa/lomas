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

    export type DeductionTypeValues = {
        name?: string | null;
        code?: string | null;
        description?: string | null;
        calculation_method?: string | null;
        rate?: string | null;
        fixed_amount?: string | null;
        is_active?: boolean;
        sort_order?: number | string | null;
    };

    let {
        action,
        method = 'post',
        type = null,
        calculationMethods = [],
        currencySymbol = '₱',
        submitLabel = 'Save deduction type',
        onSuccess = () => {},
        onCancel = () => {},
    }: {
        action: string;
        method?: 'post' | 'patch';
        type?: DeductionTypeValues | null;
        calculationMethods?: { value: string; label: string }[];
        currencySymbol?: string;
        submitLabel?: string;
        onSuccess?: () => void;
        onCancel?: () => void;
    } = $props();

    // The form is remounted for each row, so the initial values are captured on purpose.
    let calculationMethod = $state(
        untrack(() => type?.calculation_method ?? 'percentage_of_loan_amount'),
    );
    let isActive = $state(untrack(() => type?.is_active ?? true));
</script>

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
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        autocomplete="off"
                        placeholder="Documentary Stamp"
                        value={type?.name ?? ''}
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
                        placeholder="DOC_STAMP"
                        class="font-mono uppercase"
                        value={type?.code ?? ''}
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
                    value={type?.description ?? ''}
                />
                <InputError message={errors.description} />
            </div>

            <Separator />

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="calculation_method">Calculation</Label>
                    <input
                        type="hidden"
                        name="calculation_method"
                        value={calculationMethod}
                    />
                    <Select bind:value={calculationMethod}>
                        <SelectTrigger
                            id="calculation_method"
                            class="w-full"
                        >
                            {calculationMethods.find(
                                (o) => o.value === calculationMethod,
                            )?.label ?? calculationMethod}
                        </SelectTrigger>
                        <SelectContent>
                            {#each calculationMethods as option (option.value)}
                                <SelectItem value={option.value}>
                                    {option.label}
                                </SelectItem>
                            {/each}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.calculation_method} />
                </div>

                <div class="grid gap-2">
                    <Label for="rate">Rate</Label>
                    {#if calculationMethod === 'percentage_of_loan_amount'}
                        <div class="relative">
                            <Input
                                id="rate"
                                name="rate"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                                inputmode="decimal"
                                class="pr-8"
                                value={type?.rate ?? '1.00'}
                            />
                            <span
                                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                            >
                                %
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Applied to the gross loan amount.
                        </p>
                    {:else}
                        <Input
                            id="rate"
                            name="rate"
                            value={type?.rate ?? ''}
                            disabled
                        />
                        <p class="text-xs text-muted-foreground">
                            A fixed deduction does not use a rate.
                        </p>
                    {/if}
                    <InputError message={errors.rate} />
                </div>

                <div class="grid gap-2">
                    <Label for="fixed_amount">Fixed amount</Label>
                    {#if calculationMethod === 'fixed_amount'}
                        <div class="relative">
                            <Input
                                id="fixed_amount"
                                name="fixed_amount"
                                type="number"
                                min="0"
                                max="9999999999.99"
                                step="0.01"
                                required
                                inputmode="decimal"
                                class="pr-10"
                                value={type?.fixed_amount ?? '50.00'}
                            />
                            <span
                                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                            >
                                {currencySymbol}
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            The same amount on every loan.
                        </p>
                    {:else}
                        <Input
                            id="fixed_amount"
                            name="fixed_amount"
                            value={type?.fixed_amount ?? ''}
                            disabled
                        />
                        <p class="text-xs text-muted-foreground">
                            A percentage deduction does not use a fixed amount.
                        </p>
                    {/if}
                    <InputError message={errors.fixed_amount} />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="sort_order">Sort order</Label>
                    <Input
                        id="sort_order"
                        name="sort_order"
                        type="number"
                        min="0"
                        max="9999"
                        step="1"
                        required
                        inputmode="numeric"
                        value={type?.sort_order ?? 0}
                    />
                    <p class="text-xs text-muted-foreground">
                        Lower numbers appear first on the loan form.
                    </p>
                    <InputError message={errors.sort_order} />
                </div>

                <div class="grid gap-2 self-end">
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
                            <span
                                class="block text-xs text-muted-foreground"
                            >
                                Inactive types stay on loans that already use
                                them.
                            </span>
                        </span>
                    </label>
                    <InputError message={errors.is_active} />
                </div>
            </div>

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