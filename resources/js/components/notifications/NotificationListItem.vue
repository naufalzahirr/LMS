<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { isUnread, showMarkAsRead } from '@/lib/notificationDisplay';
import { formatRelativeTime } from '@/lib/utils';
import type { NotificationItem } from '@/types/notifications';

const props = defineProps<{ notification: NotificationItem }>();

function markRead(): void {
    router.patch(props.notification.read_url, {}, { preserveScroll: true });
}
</script>

<template>
    <div
        class="flex gap-3 rounded-lg p-3"
        :class="isUnread(notification) ? 'bg-primary/5' : ''"
    >
        <span
            class="mt-1.5 size-2 shrink-0 rounded-full"
            :class="isUnread(notification) ? 'bg-primary' : 'bg-transparent'"
            aria-hidden="true"
        />
        <div class="min-w-0 flex-1 space-y-1">
            <div class="flex items-start justify-between gap-2">
                <p
                    class="text-sm break-words"
                    :class="
                        isUnread(notification) ? 'font-semibold' : 'font-medium'
                    "
                >
                    {{ notification.title }}
                    <span v-if="isUnread(notification)" class="sr-only">
                        (unread)</span
                    >
                </p>
                <time
                    class="shrink-0 text-xs text-muted-foreground"
                    :datetime="notification.created_at"
                    >{{ formatRelativeTime(notification.created_at) }}</time
                >
            </div>
            <p class="text-sm break-words text-muted-foreground">
                {{ notification.message }}
            </p>
            <div class="flex flex-wrap items-center gap-3 pt-1">
                <Link
                    v-if="notification.action_url"
                    :href="notification.action_url"
                    class="text-sm font-medium text-primary hover:underline"
                >
                    {{ notification.action_label }}
                </Link>
                <Button
                    v-if="showMarkAsRead(notification)"
                    variant="ghost"
                    size="sm"
                    class="h-auto gap-1 px-2 py-1 text-xs text-muted-foreground"
                    @click="markRead"
                >
                    <Check class="size-3.5" /> Mark read
                </Button>
            </div>
        </div>
    </div>
</template>
