export type NotificationItem = {
    id: string;
    title: string;
    message: string;
    action_label: string | null;
    action_url: string | null;
    read_at: string | null;
    created_at: string;
    read_url: string;
};

export type NotificationSummary = {
    unread_count: number;
    latest: NotificationItem[];
};
