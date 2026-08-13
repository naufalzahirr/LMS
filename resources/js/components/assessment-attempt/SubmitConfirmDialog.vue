<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{
    open: boolean;
    unansweredCount: number;
    /** True while any answer is pending/saving/queued/failed — never bypassable. */
    hasUnsavedOrFailedAnswers: boolean;
    /** True when at least one answer is specifically in a failed state. */
    hasSaveErrors: boolean;
    /** True while the actual submit request is in flight. */
    submitting: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'review-unanswered': [];
    confirm: [];
}>();

const confirmDisabled = () =>
    props.submitting || props.hasUnsavedOrFailedAnswers;
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Submit this attempt?</DialogTitle>
                <DialogDescription v-if="hasSaveErrors">
                    Some answers could not be saved. Retry them before
                    submitting — this is separate from any unanswered questions,
                    and cannot be bypassed.
                </DialogDescription>
                <DialogDescription v-else-if="hasUnsavedOrFailedAnswers">
                    Saving your latest answers…
                </DialogDescription>
                <DialogDescription v-else-if="unansweredCount > 0">
                    You still have {{ unansweredCount }}
                    {{ unansweredCount === 1 ? 'question' : 'questions' }}
                    unanswered. Answers cannot be changed after you submit.
                </DialogDescription>
                <DialogDescription v-else>
                    Answers cannot be changed after you submit.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button
                    v-if="unansweredCount > 0"
                    variant="outline"
                    :disabled="submitting"
                    @click="emit('review-unanswered')"
                >
                    Review unanswered
                </Button>
                <Button :disabled="confirmDisabled()" @click="emit('confirm')">
                    {{
                        unansweredCount > 0 ? 'Submit anyway' : 'Submit attempt'
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
