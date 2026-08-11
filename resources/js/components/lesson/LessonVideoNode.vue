<script setup lang="ts">
import { computed } from 'vue';
import type { LessonNode } from '@/types/lesson-content';

const props = defineProps<{ node: LessonNode }>();
const attrs = computed(() => props.node.attrs ?? {});
const trustedEmbedUrl = computed(() => {
    const value = attrs.value.embedUrl;

    if (typeof value !== 'string') {
        return null;
    }

    try {
        const url = new URL(value);

        return url.protocol === 'https:' &&
            ['www.youtube-nocookie.com', 'player.vimeo.com'].includes(url.host)
            ? value
            : null;
    } catch {
        return null;
    }
});
</script>

<template>
    <figure v-if="trustedEmbedUrl" class="my-8">
        <div
            class="aspect-video overflow-hidden rounded-xl border bg-black shadow-sm"
        >
            <iframe
                :src="trustedEmbedUrl"
                class="size-full"
                :title="attrs.title as string"
                loading="lazy"
                allow="
                    accelerometer;
                    autoplay;
                    clipboard-write;
                    encrypted-media;
                    gyroscope;
                    picture-in-picture;
                "
                allowfullscreen
                referrerpolicy="strict-origin-when-cross-origin"
            />
        </div>
        <figcaption
            v-if="attrs.caption"
            class="mt-2 text-sm leading-6 text-muted-foreground"
        >
            {{ attrs.caption }}
        </figcaption>
    </figure>
</template>
