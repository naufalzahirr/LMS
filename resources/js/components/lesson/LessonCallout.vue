<script setup lang="ts">
import { CircleAlert, Info, Lightbulb, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import type { LessonNode } from '@/types/lesson-content';

const props = defineProps<{ node: LessonNode }>();
const type = computed(() => (props.node.attrs?.type as string) ?? 'info');
const label = computed(
    () =>
        ({
            info: 'Info',
            tip: 'Tip',
            warning: 'Warning',
            important: 'Important',
        })[type.value] ?? 'Info',
);
const classes = computed(
    () =>
        ({
            info: 'border-blue-300 bg-blue-50 text-blue-950 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-100',
            tip: 'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-100',
            warning:
                'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100',
            important:
                'border-violet-300 bg-violet-50 text-violet-950 dark:border-violet-800 dark:bg-violet-950/30 dark:text-violet-100',
        })[type.value],
);
</script>

<template>
    <aside :class="['my-7 rounded-xl border p-4', classes]">
        <div class="mb-2 flex items-center gap-2 text-sm font-semibold">
            <Info v-if="type === 'info'" class="size-4" />
            <Lightbulb v-else-if="type === 'tip'" class="size-4" />
            <TriangleAlert v-else-if="type === 'warning'" class="size-4" />
            <CircleAlert v-else class="size-4" />
            <span>{{ label }}</span>
        </div>
        <div class="leading-7">
            <slot />
        </div>
    </aside>
</template>
