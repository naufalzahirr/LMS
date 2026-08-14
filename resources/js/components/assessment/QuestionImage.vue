<script setup lang="ts">
import type { QuestionImage } from '@/types/assessment';

defineProps<{ image: QuestionImage | null; size?: 'sm' | 'md' }>();
</script>

<template>
    <img
        v-if="image"
        :src="image.url"
        :alt="image.alt_text"
        loading="lazy"
        :class="[
            'h-auto w-full rounded-lg border bg-background object-contain',
            size === 'sm' ? 'max-w-xs' : 'max-w-md',
        ]"
    />
</template>
