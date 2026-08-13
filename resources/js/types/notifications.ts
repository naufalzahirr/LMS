import type { PaginationLink } from '@/types/academic';

export type NotificationItem = {
    id: string;
    title: string;
    message: string;
    action_label: string | null;
    action_url: string | null;
    // Explicitly nullable: a fresh, never-read notification MUST carry
    // read_at === null. Never treat a missing/undefined value here as
    // read — that would hide unread notifications instead of failing loudly.
    read_at: string | null;
    created_at: string;
    read_url: string;
};

/** The global, shell-wide summary shared on every authenticated Inertia response. */
export type NotificationSummary = {
    unread_count: number;
    latest: NotificationItem[];
};

/** The Notification Center's own paginated collection — never shares a prop name with NotificationSummary. */
export type NotificationPage = {
    data: NotificationItem[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};
