import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

const RELATIVE_TIME_UNITS: Array<[Intl.RelativeTimeFormatUnit, number]> = [
    ['year', 60 * 60 * 24 * 365],
    ['month', 60 * 60 * 24 * 30],
    ['week', 60 * 60 * 24 * 7],
    ['day', 60 * 60 * 24],
    ['hour', 60 * 60],
    ['minute', 60],
];

const relativeTimeFormatter = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

export function formatRelativeTime(isoDate: string): string {
    const seconds = (new Date(isoDate).getTime() - Date.now()) / 1000;

    for (const [unit, unitSeconds] of RELATIVE_TIME_UNITS) {
        if (Math.abs(seconds) >= unitSeconds) {
            return relativeTimeFormatter.format(
                Math.round(seconds / unitSeconds),
                unit,
            );
        }
    }

    return relativeTimeFormatter.format(Math.round(seconds), 'second');
}
