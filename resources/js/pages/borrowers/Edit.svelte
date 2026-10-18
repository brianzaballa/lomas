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
    import { untrack } from 'svelte';
    import { Form, Link } from '@inertiajs/svelte';
    import BorrowerController from '@/actions/App/Http/Controllers/BorrowerController';
    import AppHead from '@/components/AppHead.svelte';
    import BorrowerFormFields, {
        type BorrowerValues,
    } from '@/components/BorrowerFormFields.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Button } from '@/components/ui/button';
    import {
        edit,
        show as showBorrower,
    } from '@/routes/borrowers';

    let {
        borrower,
        statuses,
        areas = [],
    }: {
        borrower: BorrowerValues & {
            id: string;
            borrower_number: string;
            status: string;
            default_area_id?: string | null;
        };
        statuses: { value: string; label: string }[];
        areas: Array<{ id: string; code: string; name: string }>;
    } = $props();

    let status = $state(untrack(() => borrower.status));
    let defaultAreaId = $state(untrack(() => borrower.default_area_id ?? ''));
</script>

<AppHead title="Edit {borrower.borrower_number}" />

<h1 class="sr-only">Edit borrower</h1>

<div class="flex flex-col space-y-6">
    <Heading
        variant="small"
        title="Edit {borrower.borrower_number}"
        description="Changes are recorded in the audit trail"
    />

    <Form
        {...BorrowerController.update.form({ borrower: borrower.id })}
        options={{ preserveScroll: true }}
    >
        {#snippet children({ errors, processing })}
            <div class="grid gap-6">
                <BorrowerFormFields
                    values={borrower}
                    {statuses}
                    {areas}
                    bind:status
                    bind:defaultAreaId
                    {errors}
                />

                <div class="flex items-center gap-4">
                    <Button type="submit" disabled={processing}>Save changes</Button>
                    <Button variant="outline" asChild>
                        {#snippet children(props)}
                            <Link
                                href={showBorrower({ borrower: borrower.id })}
                                class={props.class}
                            >Cancel</Link>
                        {/snippet}
                    </Button>
                </div>
            </div>
        {/snippet}
    </Form>
</div>