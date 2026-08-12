<script setup lang="ts">
import { computed, provide } from 'vue';
import LessonContentNode from '@/components/lesson/LessonContentNode.vue';
import { lessonCheckpointMasteryKey } from '@/lib/lessonCheckpointMastery';
import type {
    LessonCheckpointMasteryState,
    LessonCheckpointMasteryUpdate,
} from '@/lib/lessonCheckpointMastery';
import type { LessonDocument } from '@/types/lesson-content';

const props = defineProps<{
    document: LessonDocument;
    checkpointMasteryState?: LessonCheckpointMasteryState;
}>();
const emit = defineEmits<{
    checkpointMasteryChange: [update: LessonCheckpointMasteryUpdate];
}>();

provide(lessonCheckpointMasteryKey, {
    state: computed(() => props.checkpointMasteryState),
    update: (update) => emit('checkpointMasteryChange', update),
});
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
