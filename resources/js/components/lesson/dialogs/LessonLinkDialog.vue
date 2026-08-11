<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { validateLessonLinkUrl } from '@/lib/lessonUrls';

const props = defineProps<{
    open: boolean;
    initialUrl: string;
    initialText: string;
    editing: boolean;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
    save: [value: { url: string; text: string }];
    remove: [];
}>();

const url = ref('');
const text = ref('');
const error = ref('');

watch(
    () => props.open,
    (open) => {
        if (open) {
            url.value = props.initialUrl;
            text.value = props.initialText;
            error.value = '';
        }
    },
);

function submit(): void {
    const normalizedUrl = url.value.trim();
    const urlError = validateLessonLinkUrl(normalizedUrl);

    if (urlError) {
        error.value = urlError;

        return;
    }

    if (!text.value.trim()) {
        error.value = 'Link text is required.';

        return;
    }

    emit('save', { url: normalizedUrl, text: text.value });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <form class="space-y-5" novalidate @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Edit link' : 'Insert link'
                    }}</DialogTitle>
                    <DialogDescription>
                        Add a safe web link. It will open in a separate tab.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="lesson-link-text">Link text</Label>
                    <Input
                        id="lesson-link-text"
                        v-model="text"
                        autocomplete="off"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="lesson-link-url">URL</Label>
                    <Input
                        id="lesson-link-url"
                        v-model="url"
                        type="text"
                        inputmode="url"
                        placeholder="https://example.com/resource"
                        autocomplete="off"
                        :aria-invalid="Boolean(error)"
                        @input="error = ''"
                    />
                    <p
                        v-if="error"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ error }}
                    </p>
                </div>

                <DialogFooter class="gap-2 sm:justify-between">
                    <Button
                        v-if="editing"
                        type="button"
                        variant="destructive"
                        @click="emit('remove')"
                    >
                        Remove link
                    </Button>
                    <span v-else />
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="emit('update:open', false)"
                        >
                            Cancel
                        </Button>
                        <Button type="submit">{{
                            editing ? 'Update link' : 'Insert link'
                        }}</Button>
                    </div>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
