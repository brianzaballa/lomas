<script module lang="ts">
    import { create as createBorrower, index as borrowersIndex } from '@/routes/borrowers';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Borrowers',
                href: borrowersIndex(),
            },
            {
                title: 'New',
                href: createBorrower(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, Link } from '@inertiajs/svelte';
    import BorrowerController from '@/actions/App/Http/Controllers/BorrowerController';
    import AppHead from '@/components/AppHead.svelte';
    import BorrowerFormFields from '@/components/BorrowerFormFields.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Button } from '@/components/ui/button';

    let { statuses, areas }: { statuses: { value: string; label: string }[]; areas: Array<{ id: string; code: string; name: string }> } = $props();

    let status = $state('active');
    let defaultAreaId = $state('');
</script>

<AppHead title="New borrower" />

<h1 class="sr-only">New borrower</h1>

<div class="flex flex-col space-y-6">
    <Heading
        variant="small"
        title="New borrower"
        description="The borrower number is assigned automatically"
    />

    <Form {...BorrowerController.store.form()} options={{ preserveScroll: true }}>
        {#snippet children({ errors, processing })}
            <div class="grid gap-6">
                <BorrowerFormFields {statuses} {areas} bind:status bind:defaultAreaId {errors} />

                <div class="flex items-center gap-4">
                    <Button type="submit" disabled={processing}>Create borrower</Button>
                    <Button variant="outline" asChild>
                        {#snippet children(props)}
                            <Link href={borrowersIndex()} class={props.class}>Cancel</Link>
                        {/snippet}
                    </Button>
                </div>
            </div>
        {/snippet}
    </Form>
</div>