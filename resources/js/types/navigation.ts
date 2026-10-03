import type { LinkComponentBaseProps } from '@inertiajs/core';
import type { Component, SvelteComponent } from 'svelte';

type NavIcon =
    | Component<{ class?: string }>
    | (new (...args: any[]) => SvelteComponent<{ class?: string }>);

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<LinkComponentBaseProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<LinkComponentBaseProps['href']>;
    icon?: NavIcon;
    isActive?: boolean;
};

/**
 * A navigation entry that may not have a target yet. Modules that are not
 * built yet stay visible in the navigation as disabled entries so the
 * information architecture of the system is clear.
 */
export type NavEntry = Omit<NavItem, 'href'> & {
    href?: NonNullable<LinkComponentBaseProps['href']>;
    group?: string;
    disabled?: boolean;
    badge?: string;
};
