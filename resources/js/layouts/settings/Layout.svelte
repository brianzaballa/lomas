<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import Heading from '@/components/Heading.svelte';
    import { Button } from '@/components/ui/button';
    import { Separator } from '@/components/ui/separator';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { toUrl } from '@/lib/utils';
    import { edit as editAppearance } from '@/routes/appearance';
    import { index as deductionTypes } from '@/routes/deduction-types';
    import { index as loanProducts } from '@/routes/loan-products';
    import { edit as editProfile } from '@/routes/profile';
    import { edit as editSecurity } from '@/routes/security';
    import { edit as editSystem } from '@/routes/system-settings';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const permissions = $derived(
        (page.props.auth?.user?.permission_names ?? []) as string[],
    );

    type SettingsNavItem = { title: string; href: string };

    const sidebarNavItems: SettingsNavItem[] = $derived([
        {
            title: 'Profile',
            href: toUrl(editProfile()),
        },
        {
            title: 'Security',
            href: toUrl(editSecurity()),
        },
        {
            title: 'Appearance',
            href: toUrl(editAppearance()),
        },
        ...(permissions.includes('settings.manage')
            ? [
                  {
                      title: 'Loan Products',
                      href: toUrl(loanProducts()),
                  },
                  {
                      title: 'Deduction Types',
                      href: toUrl(deductionTypes()),
                  },
                  {
                      title: 'System',
                      href: toUrl(editSystem()),
                  },
              ]
            : []),
    ]);

    const url = currentUrlState();
</script>

<div class="px-4 py-6">
    <Heading
        title="Settings"
        description="Manage your profile, account and system settings"
    />

    <div class="flex flex-col lg:flex-row lg:space-x-12">
        <aside class="w-full max-w-xl lg:w-48">
            <nav
                class="flex flex-col space-y-1 space-x-0"
                aria-label="Settings"
            >
                {#each sidebarNavItems as item (item.href)}
                    <Button
                        variant="ghost"
                        class="w-full justify-start {url.isCurrentUrl(
                            item.href,
                            url.currentUrl,
                        )
                            ? 'bg-muted'
                            : ''}"
                        asChild
                    >
                        {#snippet children(props)}
                            <Link href={item.href} class={props.class}>
                                {item.title}
                            </Link>
                        {/snippet}
                    </Button>
                {/each}
            </nav>
        </aside>

        <Separator class="my-6 lg:hidden" />

        <div class="flex-1 md:max-w-2xl">
            <section class="max-w-xl space-y-12">
                {@render children?.()}
            </section>
        </div>
    </div>
</div>
