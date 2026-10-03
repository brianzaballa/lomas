<script lang="ts">
    import { untrack } from 'svelte';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Label } from '@/components/ui/label';

    let {
        name,
        legend,
        options,
        selected = [],
        error = '',
    }: {
        /** The submitted field name; a single value is submitted per option. */
        name: string;
        legend: string;
        options: string[];
        selected?: string[];
        error?: string;
    } = $props();

    let values = $state<string[]>(untrack(() => [...selected]));

    function toggle(option: string, checked: boolean): void {
        values = checked
            ? [...values, option]
            : values.filter((value) => value !== option);
    }
</script>

<div class="grid gap-2">
    <Label>{legend}</Label>

    {#each values as value (value)}
        <input type="hidden" name="{name}[]" {value} />
    {/each}

    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        {#each options as option (option)}
            <label
                class="flex items-center gap-2 rounded-md border border-border/70 px-3 py-2 text-sm"
            >
                <Checkbox
                    checked={values.includes(option)}
                    onCheckedChange={(checked) =>
                        toggle(option, checked === true)}
                />
                <span class="truncate">{option}</span>
            </label>
        {/each}
    </div>

    {#if error}
        <p class="text-sm text-destructive">{error}</p>
    {/if}
</div>
