<script setup lang="ts">
import { computed } from 'vue';
import type { LessonNode } from '@/types/lesson-content';

const props = defineProps<{ node: LessonNode }>();

const bold = computed(() =>
    props.node.marks?.some((mark) => mark.type === 'bold'),
);
const italic = computed(() =>
    props.node.marks?.some((mark) => mark.type === 'italic'),
);
const linkHref = computed(() => {
    const href = props.node.marks?.find((mark) => mark.type === 'link')?.attrs
        ?.href;

    if (!href) {
        return null;
    }

    try {
        return ['http:', 'https:'].includes(new URL(href).protocol)
            ? href
            : null;
    } catch {
        return null;
    }
});
</script>

<template>
    <a
        v-if="linkHref"
        :href="linkHref"
        target="_blank"
        rel="noopener noreferrer"
        class="font-medium text-primary underline decoration-primary/40 underline-offset-4 hover:decoration-primary"
    >
        <strong v-if="bold"
            ><em v-if="italic">{{ node.text }}</em
            ><template v-else>{{ node.text }}</template></strong
        >
        <em v-else-if="italic">{{ node.text }}</em>
        <template v-else>{{ node.text }}</template>
    </a>
    <strong v-else-if="bold"
        ><em v-if="italic">{{ node.text }}</em
        ><template v-else>{{ node.text }}</template></strong
    >
    <em v-else-if="italic">{{ node.text }}</em>
    <template v-else>{{ node.text }}</template>
</template>
