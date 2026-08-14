<script setup lang="ts">
import type { QuestionImage } from '@/types/assessment';

defineProps<{ image: QuestionImage | null; size?: 'sm' | 'md' }>();
</script>

<template>
    <!--
        Keyed on the URL so a replaced image mounts a fresh element rather than
        patching src on the one already holding the previous decoded bitmap.
    -->
    <img
        v-if="image"
        :key="image.url"
        :src="image.url"
        :alt="image.alt_text"
        loading="lazy"
        :class="[
            'h-auto w-full rounded-lg border bg-background object-contain',
            size === 'sm' ? 'max-w-xs' : 'max-w-md',
        ]"
    />
</template>
