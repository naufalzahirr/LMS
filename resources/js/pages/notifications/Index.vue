<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BellOff } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import NotificationListItem from '@/components/notifications/NotificationListItem.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { NotificationPage } from '@/types/notifications';

const props = defineProps<{
    notificationPage: NotificationPage;
    unreadCount: number;
    markAllReadUrl: string;
}>();

function markAllRead(): void {
    router.post(props.markAllReadUrl, {}, { preserveScroll: true });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Notifications', href: '/notifications' },
        ],
    },
});
</script>

<template>
    <Head title="Notifications" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                title="Notifications"
                description="Meaningful events across your learning activity."
            />
            <Button
                v-if="unreadCount > 0"
                variant="outline"
                @click="markAllRead"
            >
                Mark all as read
            </Button>
        </div>

        <Card v-if="!notificationPage.data.length">
            <CardContent
                class="flex flex-col items-center gap-3 py-14 text-center text-muted-foreground"
            >
                <BellOff class="size-9" />
                <p>No notifications yet.</p>
            </CardContent>
        </Card>

        <Card v-else class="py-2">
            <CardContent class="divide-y px-2">
                <NotificationListItem
                    v-for="notification in notificationPage.data"
                    :key="notification.id"
                    :notification="notification"
                />
            </CardContent>
        </Card>

        <PaginationLinks
            :links="notificationPage.links"
            :from="notificationPage.from"
            :to="notificationPage.to"
            :total="notificationPage.total"
            item-label="notifications"
        />
    </div>
</template>
