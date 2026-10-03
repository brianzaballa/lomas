<script lang="ts">
    import Search from '@lucide/svelte/icons/search';
    import { Input } from '@/components/ui/input';
    import { cn } from '@/lib/utils';

    let {
        value = $bindable(''),
        name,
        type = 'search',
        placeholder = '',
        list,
        ariaLabel,
        class: className = '',
        oninput,
    }: {
        value?: string;
        name: string;
        type?: string;
        placeholder?: string;
        /** Id of a datalist with suggested values. */
        list?: string;
        ariaLabel?: string;
        class?: string;
        oninput?: (event: Event) => void;
    } = $props();
</script>

<div class="relative">
    <Search
        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
    />
    <Input
        {name}
        {type}
        {placeholder}
        {list}
        aria-label={ariaLabel ?? placeholder}
        class={cn('pl-8', className)}
        bind:value
        {oninput}
    />
</div>