<script setup lang="ts">
import { computed } from 'vue';
import type { LessonNode } from '@/types/lesson-content';

const props = defineProps<{ node: LessonNode }>();
const attrs = computed(() => props.node.attrs ?? {});
const widthClass = computed(
    () =>
        ({
            small: 'max-w-sm',
            medium: 'max-w-xl',
            large: 'max-w-3xl',
            full: 'max-w-none',
        })[(attrs.value.size as string) ?? 'large'] ?? 'max-w-3xl',
);
const alignmentClass = computed(
    () =>
        ({ left: 'mr-auto', center: 'mx-auto', right: 'ml-auto' })[
            (attrs.value.alignment as string) ?? 'center'
        ] ?? 'mx-auto',
);
</script>

<template>
    <figure :class="['my-8 w-full', widthClass, alignmentClass]">
        <img
            :src="attrs.url as string"
            :alt="attrs.decorative ? '' : (attrs.altText as string)"
            class="h-auto max-h-[48rem] w-full rounded-xl border bg-muted/30 object-contain shadow-sm"
            loading="lazy"
        />
        <figcaption
            v-if="attrs.caption"
            class="mt-2 text-center text-sm leading-6 text-muted-foreground"
        >
            {{ attrs.caption }}
        </figcaption>
    </figure>
</template>
