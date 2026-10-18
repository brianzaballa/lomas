<script module lang="ts">
    import { index as areasIndex } from '@/routes/areas';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Areas',
                href: areasIndex(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import Pencil from '@lucide/svelte/icons/pencil';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { store as storeArea, update as updateArea } from '@/routes/areas';
    import { toUrl } from '@/lib/utils';

    let { areas = [] }: { areas?: any[] } = $props();
    let editing = $state<string | 'new' | null>(null);
    const editingArea = $derived(areas.find((area) => area.id === editing) ?? null);
</script>

<AppHead title="Areas" />

<div class="flex flex-col space-y-6">
    <Heading
        variant="small"
        title="Areas"
        description="Lookup list used to group nodes and the loans they own."
    />

    {#if editing === 'new'}
        <Form {...storeArea.form()} options={{ preserveScroll: true }}>
            {#snippet children({ errors, processing })}
                <div class="grid gap-4 rounded-md border p-4">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="code">Code</Label>
                            <Input id="code" name="code" required />
                            <p class="text-xs text-destructive">{errors.code}</p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" name="name" required />
                            <p class="text-xs text-destructive">{errors.name}</p>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox name="is_active" checked />
                        Active
                    </label>
                    <div class="flex gap-2">
                        <Button type="submit" disabled={processing}>Create</Button>
                        <Button variant="outline" type="button" onclick={() => (editing = null)}>Cancel</Button>
                    </div>
                </div>
            {/snippet}
        </Form>
    {:else if editing !== null && editingArea}
        <Form {...updateArea.form({ area: editingArea.id })} options={{ preserveScroll: true }}>
            {#snippet children({ errors, processing })}
                <div class="grid gap-4 rounded-md border p-4">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="code">Code</Label>
                            <Input id="code" name="code" value={editingArea.code} required />
                            <p class="text-xs text-destructive">{errors.code}</p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" name="name" value={editingArea.name} required />
                            <p class="text-xs text-destructive">{errors.name}</p>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox name="is_active" checked={editingArea.is_active} />
                        Active
                    </label>
                    <div class="flex gap-2">
                        <Button type="submit" disabled={processing}>Save</Button>
                        <Button variant="outline" type="button" onclick={() => (editing = null)}>Cancel</Button>
                    </div>
                </div>
            {/snippet}
        </Form>
    {:else}
        <div class="flex justify-end">
            <Button size="sm" onclick={() => (editing = 'new')}>
                <Plus class="size-4" />
                Add area
            </Button>
        </div>

        <div class="rounded-md border">
            <div class="grid gap-0">
                {#each areas as area (area.id)}
                    <div class="flex items-center justify-between border-b p-4 last:border-b-0">
                        <div>
                            <div class="font-medium">{area.name}</div>
                            <div class="font-mono text-xs text-muted-foreground">{area.code}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            {#if area.is_active}
                                <Badge variant="outline">Active</Badge>
                            {:else}
                                <Badge variant="secondary" class="opacity-70">Inactive</Badge>
                            {/if}
                            <Button size="sm" variant="outline" onclick={() => (editing = area.id)}>
                                <Pencil class="size-4" />
                                Edit
                            </Button>
                        </div>
                    </div>
                {:else}
                    <div class="p-8 text-center text-sm text-muted-foreground">No areas yet.</div>
                {/each}
            </div>
        </div>
    {/if}
</div>
