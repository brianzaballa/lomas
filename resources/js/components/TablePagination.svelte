<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import { Button } from '@/components/ui/button';
    import { cn } from '@/lib/utils';
    import type { Paginated } from '@/types';

    let {
        meta,
    }: {
        meta: Paginated<unknown>;
    } = $props();

    type PageLink = Paginated<unknown>['links'][number];

    function isPrevious(link: PageLink): boolean {
        return link.label.includes('Previous');
    }

    function isNext(link: PageLink): boolean {
        return link.label.includes('Next');
    }

    function isNumber(link: PageLink): boolean {
        return /^\d+$/.test(link.label.trim());
    }

    function isEllipsis(link: PageLink): boolean {
        return link.label.includes('...');
    }

    function pageLink(link: PageLink) {
        return {
            ...link,
            href: link.url ?? '#',
            disabled: !link.url,
        };
    }
</script>

{#if meta.last_page > 1}
    <div
        class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
    >
        <p>
            Showing <span class="font-medium text-foreground">{meta.from ?? 0}</span>
            to <span class="font-medium text-foreground">{meta.to ?? 0}</span>
            of <span class="font-medium text-foreground">{meta.total}</span>
        </p>

        <div class="flex items-center gap-1">
            {#each meta.links as link, index (index)}
                {#if isPrevious(link)}
                    {@const previous = pageLink(link)}
                    <Button asChild variant="outline" size="icon">
                        {#snippet children(props)}
                            <Link
                                {...props}
                                href={previous.href}
                                aria-label="Previous page"
                                class={cn(
                                    props.class,
                                    previous.disabled &&
                                        'pointer-events-none opacity-50',
                                )}
                            >
                                <ChevronLeft class="size-4" />
                            </Link>
                        {/snippet}
                    </Button>
                {:else if isNext(link)}
                    {@const next = pageLink(link)}
                    <Button asChild variant="outline" size="icon">
                        {#snippet children(props)}
                            <Link
                                {...props}
                                href={next.href}
                                aria-label="Next page"
                                class={cn(
                                    props.class,
                                    next.disabled &&
                                        'pointer-events-none opacity-50',
                                )}
                            >
                                <ChevronRight class="size-4" />
                            </Link>
                        {/snippet}
                    </Button>
                {:else if isNumber(link)}
                    {@const page = pageLink(link)}
                    <Button
                        asChild
                        variant={page.active ? 'outline' : 'ghost'}
                        size="icon"
                    >
                        {#snippet children(props)}
                            <Link
                                {...props}
                                href={page.href}
                                aria-current={page.active ? 'page' : undefined}
                                class={cn(props.class, 'tabular-nums')}
                            >
                                {page.label.trim()}
                            </Link>
                        {/snippet}
                    </Button>
                {:else if isEllipsis(link)}
                    <span class="px-2 text-muted-foreground">&hellip;</span>
                {/if}
            {/each}
        </div>
    </div>
{/if}