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

defineProps<{
    open: boolean;
    unansweredCount: number;
    submitting: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'review-unanswered': [];
    confirm: [];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Submit this attempt?</DialogTitle>
                <DialogDescription v-if="unansweredCount > 0">
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
                <Button :disabled="submitting" @click="emit('confirm')">
                    {{
                        unansweredCount > 0 ? 'Submit anyway' : 'Submit attempt'
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
