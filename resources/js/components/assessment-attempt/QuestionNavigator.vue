<script setup lang="ts">
import { Check } from '@lucide/vue';
import { cn } from '@/lib/utils';
import type { QuestionType } from '@/types/assessment';

const props = defineProps<{
    questions: { id: number; question_type: QuestionType }[];
    answeredIds: Set<number>;
    currentId: number | null;
}>();

const emit = defineEmits<{ navigate: [id: number] }>();

function label(index: number): string {
    return String(index + 1);
}

function ariaLabel(question: { id: number }, index: number): string {
    const answered = props.answeredIds.has(question.id)
        ? 'answered'
        : 'unanswered';

    return `Question ${index + 1}, ${answered}`;
}
</script>

<template>
    <nav aria-label="Question navigator" class="flex flex-wrap gap-2">
        <button
            v-for="(question, index) in questions"
            :key="question.id"
            type="button"
            :aria-label="ariaLabel(question, index)"
            :aria-current="question.id === currentId ? 'true' : undefined"
            :class="
                cn(
                    'flex size-8 items-center justify-center rounded-full border text-sm font-medium transition-colors',
                    question.id === currentId
                        ? 'border-primary bg-primary text-primary-foreground'
                        : answeredIds.has(question.id)
                          ? 'border-green-600/40 bg-green-50 text-green-700 dark:border-green-500/30 dark:bg-green-950/40 dark:text-green-400'
                          : 'border-border bg-background text-muted-foreground hover:bg-accent',
                )
            "
            @click="emit('navigate', question.id)"
        >
            <Check
                v-if="answeredIds.has(question.id) && question.id !== currentId"
                class="size-4"
            />
            <span v-else>{{ label(index) }}</span>
        </button>
    </nav>
</template>
