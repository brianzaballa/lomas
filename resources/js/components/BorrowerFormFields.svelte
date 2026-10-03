<script lang="ts">
    import InputError from '@/components/InputError.svelte';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
    } from '@/components/ui/select';
    import { Textarea } from '@/components/ui/textarea';

    export type BorrowerValues = {
        first_name?: string | null;
        middle_name?: string | null;
        last_name?: string | null;
        suffix?: string | null;
        birth_date?: string | null;
        mobile_number?: string | null;
        address?: string | null;
    };

    let {
        values = {},
        statuses = [],
        status = $bindable('active'),
        errors = {},
    }: {
        values?: BorrowerValues;
        statuses?: { value: string; label: string }[];
        status?: string;
        errors?: Record<string, string | undefined>;
    } = $props();
</script>

<div class="grid gap-4 sm:grid-cols-2">
    <div class="grid gap-2">
        <Label for="first_name">First name</Label>
        <Input
            id="first_name"
            name="first_name"
            required
            autocomplete="off"
            placeholder="Juan"
            value={values.first_name ?? ''}
        />
        <InputError message={errors.first_name} />
    </div>

    <div class="grid gap-2">
        <Label for="middle_name">Middle name</Label>
        <Input
            id="middle_name"
            name="middle_name"
            autocomplete="off"
            placeholder="Optional"
            value={values.middle_name ?? ''}
        />
        <InputError message={errors.middle_name} />
    </div>

    <div class="grid gap-2">
        <Label for="last_name">Last name</Label>
        <Input
            id="last_name"
            name="last_name"
            required
            autocomplete="off"
            placeholder="Dela Cruz"
            value={values.last_name ?? ''}
        />
        <InputError message={errors.last_name} />
    </div>

    <div class="grid gap-2">
        <Label for="suffix">Suffix</Label>
        <Input
            id="suffix"
            name="suffix"
            autocomplete="off"
            placeholder="Jr., III"
            value={values.suffix ?? ''}
        />
        <InputError message={errors.suffix} />
    </div>

    <div class="grid gap-2">
        <Label for="birth_date">Birth date</Label>
        <Input
            id="birth_date"
            name="birth_date"
            type="date"
            value={values.birth_date ?? ''}
        />
        <InputError message={errors.birth_date} />
    </div>

    <div class="grid gap-2">
        <Label for="mobile_number">Mobile number</Label>
        <Input
            id="mobile_number"
            name="mobile_number"
            required
            inputmode="tel"
            autocomplete="off"
            placeholder="0917 000 0000"
            value={values.mobile_number ?? ''}
        />
        <InputError message={errors.mobile_number} />
    </div>

    <div class="grid gap-2 sm:col-span-2">
        <Label for="address">Address</Label>
        <Textarea
            id="address"
            name="address"
            required
            rows={3}
            placeholder="Street, barangay, city"
            value={values.address ?? ''}
        ></Textarea>
        <InputError message={errors.address} />
    </div>

    <div class="grid gap-2">
        <Label for="status">Status</Label>
        <input type="hidden" name="status" value={status} />
        <Select bind:value={status}>
            <SelectTrigger id="status" class="w-full" aria-label="Status">
                {statuses.find((option) => option.value === status)?.label ??
                    'Select a status'}
            </SelectTrigger>
            <SelectContent>
                {#each statuses as option (option.value)}
                    <SelectItem value={option.value}>{option.label}</SelectItem>
                {/each}
            </SelectContent>
        </Select>
        <p class="text-xs text-muted-foreground">
            An inactive borrower keeps all loans and payments but cannot receive
            a new one.
        </p>
        <InputError message={errors.status} />
    </div>
</div>