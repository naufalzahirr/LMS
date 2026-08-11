<script setup lang="ts">
import LessonContentNode from '@/components/lesson/LessonContentNode.vue';
import type { LessonDocument } from '@/types/lesson-content';

defineProps<{ document: LessonDocument }>();
</script>

<template>
    <article class="lesson-content text-[1.05rem] text-foreground/90">
        <LessonContentNode
            v-for="(node, index) in document.content"
            :key="index"
            :node="node"
        />
    </article>
</template>
