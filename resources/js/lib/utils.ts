import type { LinkComponentBaseProps } from '@inertiajs/core';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(
    href: NonNullable<LinkComponentBaseProps['href']>,
): string {
    return typeof href === 'string' ? href : href.url;
}

/**
 * Element references and children handling used by the UI primitives.
 */
export type WithElementRef<
    Props,
    Element extends HTMLElement = HTMLElement,
> = Props & {
    ref?: Element | null;
};

export type WithoutChildren<Props> = Omit<Props, 'children'>;
