<script lang="ts">
    import { untrack } from 'svelte';
    import { Checkbox } from '@/components/ui/checkbox';

    let {
        name,
        value,
        label,
        checked = false,
        description = '',
    }: {
        /** The submitted field name; the permission code is the submitted value. */
        name: string;
        value: string;
        label: string;
        checked?: boolean;
        description?: string;
    } = $props();

    let isChecked = $state(untrack(() => checked));
</script>

{#if isChecked}
    <input type="hidden" {name} {value} />
{/if}

<label
    class="flex items-start gap-2 rounded-md border border-border/70 px-3 py-2 text-sm"
>
    <Checkbox class="mt-0.5" bind:checked={isChecked} />
    <span class="min-w-0">
        <span class="block truncate">{label}</span>
        {#if description}
            <span class="block text-xs text-muted-foreground">{description}</span>
        {/if}
    </span>
</label>