<script module lang="ts">
    import { edit as editSystem } from '@/routes/system-settings';

    export const layout = {
        breadcrumbs: [
            {
                title: 'System settings',
                href: editSystem(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import SystemSettingsController from '@/actions/App/Http/Controllers/Settings/SystemSettingsController';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Separator } from '@/components/ui/separator';
    import { Textarea } from '@/components/ui/textarea';

    let { settings, placeholders, updatedAt } = $props();

    const hints = $derived(
        placeholders.map((placeholder: string) => {
            if (placeholder === '{year}') return 'four digit year';
            if (placeholder === '{month}') return 'two digit month';
            if (placeholder === '{seq}') return 'sequence number';
            if (placeholder === '{seq:N}') return 'sequence padded to N digits';

            return placeholder;
        }),
    );
</script>

<AppHead title="System settings" />

<h1 class="sr-only">System settings</h1>

<div class="flex flex-col space-y-6">
    <Heading
        variant="small"
        title="System settings"
        description="Business details, currency and the number formats used for borrowers and loans"
    />

    <Form {...SystemSettingsController.update.form()} options={{ preserveScroll: true }}>
        {#snippet children({ errors, processing })}
            <div class="grid gap-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="business_name">Business name</Label>
                        <Input
                            id="business_name"
                            name="business_name"
                            value={settings.business_name}
                            required
                            autocomplete="off"
                        />
                        <InputError message={errors.business_name} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="contact_email">Contact email</Label>
                        <Input
                            id="contact_email"
                            name="contact_email"
                            type="email"
                            value={settings.contact_email ?? ''}
                            autocomplete="off"
                        />
                        <InputError message={errors.contact_email} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="contact_phone">Contact number</Label>
                        <Input
                            id="contact_phone"
                            name="contact_phone"
                            value={settings.contact_phone ?? ''}
                            autocomplete="off"
                        />
                        <InputError message={errors.contact_phone} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="currency">Currency code</Label>
                        <Input
                            id="currency"
                            name="currency"
                            value={settings.currency}
                            maxlength={3}
                            required
                            autocomplete="off"
                            class="uppercase"
                        />
                        <InputError message={errors.currency} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="currency_symbol">Currency symbol</Label>
                        <Input
                            id="currency_symbol"
                            name="currency_symbol"
                            value={settings.currency_symbol}
                            required
                            autocomplete="off"
                        />
                        <InputError message={errors.currency_symbol} />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="business_address">Business address</Label>
                    <Textarea
                        id="business_address"
                        name="business_address"
                        value={settings.business_address ?? ''}
                        rows={3}
                    />
                    <InputError message={errors.business_address} />
                </div>

                <Separator />

                <div class="space-y-1">
                    <p class="text-sm font-medium">Number formats</p>
                    <p class="text-sm text-muted-foreground">
                        Available placeholders:
                        {placeholders.join(', ')} — {hints.join('; ')}. The
                        sequence placeholder is required so that every record
                        receives a unique number.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="loan_number_format">Loan number format</Label>
                        <Input
                            id="loan_number_format"
                            name="loan_number_format"
                            value={settings.loan_number_format}
                            required
                            autocomplete="off"
                            class="font-mono"
                        />
                        <InputError message={errors.loan_number_format} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="borrower_number_format">
                            Borrower number format
                        </Label>
                        <Input
                            id="borrower_number_format"
                            name="borrower_number_format"
                            value={settings.borrower_number_format}
                            required
                            autocomplete="off"
                            class="font-mono"
                        />
                        <InputError
                            message={errors.borrower_number_format}
                        />
                    </div>
                </div>

                <Separator />

                <div class="space-y-1">
                    <p class="text-sm font-medium">Notes Collection Report</p>
                    <p class="text-sm text-muted-foreground">
                        The period the report is posted in. Weekly runs Monday
                        to Saturday; the decile periods run 1st to 10th, 11th to
                        20th, and 21st to the last day of the month.
                    </p>
                </div>

                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="ncr_period_mode">Report period</Label>
                        <select
                            id="ncr_period_mode"
                            name="ncr_period_mode"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option
                                value="weekly"
                                selected={settings.ncr_period_mode === 'weekly'}
                            >
                                Weekly (Monday to Saturday)
                            </option>
                            <option
                                value="decile"
                                selected={settings.ncr_period_mode === 'decile'}
                            >
                                Decile (1–10, 11–20, 21–end)
                            </option>
                        </select>
                        <InputError message={errors.ncr_period_mode} />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="reason">Reason for change</Label>
                    <Textarea
                        id="reason"
                        name="reason"
                        rows={2}
                        placeholder="Optional. Stored in the audit trail."
                    />
                    <InputError message={errors.reason} />
                </div>

                <div class="flex items-center gap-4">
                    <Button type="submit" disabled={processing}>Save</Button>
                    {#if updatedAt}
                        <span class="text-sm text-muted-foreground">
                            Last updated {updatedAt}
                        </span>
                    {/if}
                </div>
            </div>
        {/snippet}
    </Form>
</div>
