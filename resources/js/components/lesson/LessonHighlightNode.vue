<script setup lang="ts">
import type { RootContent } from 'hast';

defineProps<{ node: RootContent }>();

function classes(node: RootContent): string[] {
    if (node.type !== 'element') {
        return [];
    }

    const value = node.properties.className;

    if (Array.isArray(value)) {
        return value.map(String);
    }

    if (typeof value === 'string') {
        return [value];
    }

    return [];
}
</script>

<template>
    <template v-if="node.type === 'text'">{{ node.value }}</template>
    <span v-else-if="node.type === 'element'" :class="classes(node)">
        <LessonHighlightNode
            v-for="(child, index) in node.children ?? []"
            :key="index"
            :node="child"
        />
    </span>
</template>
