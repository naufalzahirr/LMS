<script setup lang="ts">
import { AlertCircle, Check, Loader2 } from '@lucide/vue';
import type { AnswerSaveState } from '@/lib/assessmentAttempt';

defineProps<{ state: AnswerSaveState }>();
</script>

<template>
    <span
        class="inline-flex items-center gap-1 text-xs text-muted-foreground"
        role="status"
    >
        <template v-if="state === 'saving'">
            <Loader2 class="size-3.5 animate-spin" /> Saving…
        </template>
        <template v-else-if="state === 'saved'">
            <Check class="size-3.5 text-green-600 dark:text-green-500" />
            Saved
        </template>
        <template v-else-if="state === 'error'">
            <AlertCircle class="size-3.5 text-destructive" />
            <span class="text-destructive">Unable to save</span>
        </template>
    </span>
</template>
