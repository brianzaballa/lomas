<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import {
        SidebarGroup,
        SidebarGroupLabel,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { toUrl } from '@/lib/utils';
    import type { NavEntry } from '@/types';

    let {
        items = [],
    }: {
        items: NavEntry[];
    } = $props();

    type NavGroup = {
        label: string;
        items: NavEntry[];
    };

    const url = currentUrlState();

    const groups = $derived<NavGroup[]>(
        Array.from(
            items.reduce<Map<string, NavEntry[]>>((carry, item) => {
                const label = item.group ?? '';

                carry.set(label, [...(carry.get(label) ?? []), item]);

                return carry;
            }, new Map<string, NavEntry[]>()),
        ).map(([label, groupItems]) => ({ label, items: groupItems })),
    );
</script>

{#each groups as group (group.label)}
    <SidebarGroup class="px-2 py-0">
        {#if group.label}
            <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
        {/if}
        <SidebarMenu>
            {#each group.items as item (item.href ? toUrl(item.href!) : item.title)}
                <SidebarMenuItem>
                    {#if item.disabled || !item.href}
                        <SidebarMenuButton
                            disabled
                            tooltip={item.badge
                                ? `${item.title} (${item.badge})`
                                : item.title}
                        >
                            {#if item.icon}
                                <item.icon class="size-4 shrink-0" />
                            {/if}
                            <span>{item.title}</span>
                            {#if item.badge}
                                <span
                                    class="ml-auto text-[10px] tracking-wide text-muted-foreground"
                                >
                                    {item.badge}
                                </span>
                            {/if}
                        </SidebarMenuButton>
                    {:else}
                        <SidebarMenuButton
                            asChild
                            isActive={url.isCurrentUrl(
                                item.href,
                                url.currentUrl,
                            )}
                            tooltip={item.title}
                        >
                            {#snippet children(props)}
                                <Link
                                    {...props}
                                    href={toUrl(item.href!)}
                                    class={props.class}
                                >
                                    {#if item.icon}
                                        <item.icon class="size-4 shrink-0" />
                                    {/if}
                                    <span>{item.title}</span>
                                </Link>
                            {/snippet}
                        </SidebarMenuButton>
                    {/if}
                </SidebarMenuItem>
            {/each}
        </SidebarMenu>
    </SidebarGroup>
{/each}