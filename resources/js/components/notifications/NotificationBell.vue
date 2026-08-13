<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import NotificationListItem from '@/components/notifications/NotificationListItem.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    showMarkAllAsRead,
    showUnreadBadge,
    unreadBadgeLabel,
} from '@/lib/notificationDisplay';
import { index as notificationsIndex, readAll } from '@/routes/notifications';

const page = usePage();
const summary = computed(() => page.props.notificationSummary);
const badgeLabel = computed(() => unreadBadgeLabel(summary.value.unread_count));

function markAllRead(): void {
    router.post(readAll.url(), {}, { preserveScroll: true });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative h-9 w-9"
                aria-label="Notifications"
            >
                <Bell class="size-5" />
                <Badge
                    v-if="showUnreadBadge(summary.unread_count)"
                    variant="destructive"
                    class="absolute -top-1 -right-1 h-4 min-w-4 justify-center rounded-full px-1 text-[10px] leading-none"
                >
                    <span class="sr-only">
                        {{ summary.unread_count }} unread notifications</span
                    >
                    <span aria-hidden="true">{{ badgeLabel }}</span>
                </Badge>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            side="bottom"
            align="end"
            :collision-padding="16"
            class="w-[calc(100vw-2rem)] max-w-sm p-0"
        >
            <div class="flex items-center justify-between px-3 py-2">
                <p class="text-sm font-semibold">Notifications</p>
                <Button
                    v-if="showMarkAllAsRead(summary.unread_count)"
                    variant="ghost"
                    size="sm"
                    class="h-auto px-2 py-1 text-xs"
                    @click="markAllRead"
                >
                    Mark all as read
                </Button>
            </div>
            <DropdownMenuSeparator />
            <div class="max-h-96 space-y-1 overflow-y-auto p-1">
                <NotificationListItem
                    v-for="notification in summary.latest"
                    :key="notification.id"
                    :notification="notification"
                />
                <p
                    v-if="!summary.latest.length"
                    class="p-4 text-center text-sm text-muted-foreground"
                >
                    You're all caught up.
                </p>
            </div>
            <DropdownMenuSeparator />
            <div class="p-1">
                <Link
                    :href="notificationsIndex()"
                    class="block rounded-md px-2 py-2 text-center text-sm text-primary hover:bg-accent"
                >
                    View all notifications
                </Link>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
