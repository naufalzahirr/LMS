<script setup lang="ts">
import { AlertCircle, Check, Loader2, RotateCcw } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { AutosaveState } from '@/lib/answerAutosave';

defineProps<{ state: AutosaveState }>();
const emit = defineEmits<{ retry: [] }>();
</script>

<template>
    <span class="inline-flex items-center gap-2 text-xs" role="status">
        <template v-if="state === 'dirty'">
            <span class="text-muted-foreground">Pending…</span>
        </template>
        <template v-else-if="state === 'saving'">
            <span class="inline-flex items-center gap-1 text-muted-foreground">
                <Loader2 class="size-3.5 animate-spin" /> Saving…
            </span>
        </template>
        <template v-else-if="state === 'saved'">
            <span
                class="inline-flex items-center gap-1 text-green-600 dark:text-green-500"
            >
                <Check class="size-3.5" /> Saved
            </span>
        </template>
        <template v-else-if="state === 'error'">
            <span class="inline-flex items-center gap-1 text-destructive">
                <AlertCircle class="size-3.5" /> Unable to save
            </span>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-auto gap-1 px-2 py-0.5 text-xs"
                @click="emit('retry')"
            >
                <RotateCcw class="size-3.5" /> Retry save
            </Button>
        </template>
    </span>
</template>
