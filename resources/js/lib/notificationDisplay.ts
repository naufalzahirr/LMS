export type NotificationReadState = {
    read_at: string | null;
};

/**
 * Single source of truth for "is this notification unread" — never treat a
 * missing/undefined read_at as read; only an actual timestamp counts as read.
 */
export function isUnread(notification: NotificationReadState): boolean {
    return notification.read_at === null || notification.read_at === undefined;
}

export function showUnreadBadge(unreadCount: number): boolean {
    return unreadCount > 0;
}

export function unreadBadgeLabel(unreadCount: number): string {
    return unreadCount > 99 ? '99+' : String(unreadCount);
}

export function showMarkAllAsRead(unreadCount: number): boolean {
    return unreadCount > 0;
}

export function showMarkAsRead(notification: NotificationReadState): boolean {
    return isUnread(notification);
}
