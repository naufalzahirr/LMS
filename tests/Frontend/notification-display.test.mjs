import assert from 'node:assert/strict';
import test from 'node:test';
import {
    isUnread,
    showMarkAllAsRead,
    showMarkAsRead,
    showUnreadBadge,
    unreadBadgeLabel,
} from '../../resources/js/lib/notificationDisplay.ts';

test('unread summary with unread_count = 2 shows the badge and mark-all-as-read', () => {
    assert.equal(showUnreadBadge(2), true);
    assert.equal(unreadBadgeLabel(2), '2');
    assert.equal(showMarkAllAsRead(2), true);
});

test('unread summary with unread_count = 0 hides the badge and mark-all-as-read', () => {
    assert.equal(showUnreadBadge(0), false);
    assert.equal(showMarkAllAsRead(0), false);
});

test('unread_count above 99 renders the capped 99+ label', () => {
    assert.equal(unreadBadgeLabel(150), '99+');
});

test('a notification with read_at = null is unread and exposes mark read', () => {
    const notification = { read_at: null };

    assert.equal(isUnread(notification), true);
    assert.equal(showMarkAsRead(notification), true);
});

test('a notification with a read_at timestamp is read and hides mark read', () => {
    const notification = { read_at: '2026-08-12T10:00:00+00:00' };

    assert.equal(isUnread(notification), false);
    assert.equal(showMarkAsRead(notification), false);
});

test('a malformed notification with undefined read_at fails toward unread, never toward hidden', () => {
    const notification = {};

    assert.equal(isUnread(notification), true);
    assert.equal(showMarkAsRead(notification), true);
});
