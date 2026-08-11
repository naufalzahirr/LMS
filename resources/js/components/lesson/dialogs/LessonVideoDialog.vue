<script setup lang="ts">
import { computed, ref, watch } from 'vue';
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
import { Textarea } from '@/components/ui/textarea';
import { parseTrustedVideo, validateLessonVideoUrl } from '@/lib/lessonUrls';

const props = defineProps<{
    open: boolean;
    editing: boolean;
    initialUrl: string;
    initialTitle: string;
    initialCaption: string;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
    save: [value: { url: string; title: string; caption: string | null }];
}>();

const url = ref('');
const title = ref('');
const caption = ref('');
const error = ref('');
const preview = computed(() => parseTrustedVideo(url.value));

watch(
    () => props.open,
    (open) => {
        if (open) {
            url.value = props.initialUrl;
            title.value = props.initialTitle;
            caption.value = props.initialCaption;
            error.value = '';
        }
    },
);

function submit(): void {
    const urlError = validateLessonVideoUrl(url.value);

    if (urlError) {
        error.value = urlError;

        return;
    }

    if (!title.value.trim()) {
        error.value = 'A descriptive video title is required.';

        return;
    }

    emit('save', {
        url: url.value.trim(),
        title: title.value.trim(),
        caption: caption.value.trim() || null,
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-h-[calc(100dvh-2rem)] overflow-hidden p-0 sm:max-w-2xl"
        >
            <form
                class="flex max-h-[calc(100dvh-2rem)] min-h-0 flex-col"
                novalidate
                @submit.prevent="submit"
            >
                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-6">
                    <DialogHeader>
                        <DialogTitle>{{
                            editing ? 'Edit video' : 'Add external video'
                        }}</DialogTitle>
                        <DialogDescription>
                            Paste a YouTube or Vimeo URL. Video files are not
                            uploaded to the LMS.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="lesson-video-url">Video URL</Label>
                        <Input
                            id="lesson-video-url"
                            v-model="url"
                            type="text"
                            inputmode="url"
                            placeholder="https://www.youtube.com/watch?v=…"
                            :aria-invalid="Boolean(error)"
                            @input="error = ''"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="lesson-video-title"
                            >Descriptive title</Label
                        >
                        <Input
                            id="lesson-video-title"
                            v-model="title"
                            maxlength="255"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="lesson-video-caption"
                            >Caption (optional)</Label
                        >
                        <Textarea
                            id="lesson-video-caption"
                            v-model="caption"
                            maxlength="2000"
                        />
                    </div>

                    <div v-if="preview" class="space-y-2">
                        <p class="text-xs font-medium text-muted-foreground">
                            {{ preview.provider }} preview
                        </p>
                        <div
                            class="aspect-video overflow-hidden rounded-lg border bg-black"
                        >
                            <iframe
                                :src="preview.embedUrl"
                                :title="title || 'Video preview'"
                                class="size-full"
                                allowfullscreen
                                referrerpolicy="strict-origin-when-cross-origin"
                            />
                        </div>
                    </div>
                    <p
                        v-if="error"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ error }}
                    </p>
                </div>

                <DialogFooter class="shrink-0 border-t bg-background px-6 py-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                    >
                        Cancel
                    </Button>
                    <Button type="submit">{{
                        editing ? 'Update video' : 'Insert video'
                    }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
