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
    if (!preview.value) {
        error.value = 'Use a supported YouTube or Vimeo URL.';

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

function parseTrustedVideo(
    value: string,
): { provider: string; embedUrl: string } | null {
    try {
        const parsed = new URL(value.trim());

        if (
            !['http:', 'https:'].includes(parsed.protocol) ||
            parsed.username ||
            parsed.password
        ) {
            return null;
        }

        const host = parsed.hostname.toLowerCase();
        const segments = parsed.pathname.split('/').filter(Boolean);
        let id: string | null = null;

        if (
            ['youtube.com', 'www.youtube.com', 'm.youtube.com'].includes(host)
        ) {
            id =
                segments[0] === 'embed' || segments[0] === 'shorts'
                    ? (segments[1] ?? null)
                    : parsed.searchParams.get('v');
        } else if (host === 'youtu.be') {
            id = segments[0] ?? null;
        }

        if (id && /^[A-Za-z0-9_-]{6,20}$/.test(id)) {
            return {
                provider: 'YouTube',
                embedUrl: `https://www.youtube-nocookie.com/embed/${id}`,
            };
        }

        if (['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'].includes(host)) {
            id = segments.at(-1) ?? null;

            if (id && /^\d+$/.test(id)) {
                return {
                    provider: 'Vimeo',
                    embedUrl: `https://player.vimeo.com/video/${id}`,
                };
            }
        }
    } catch {
        return null;
    }

    return null;
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-2xl">
            <form class="space-y-5" @submit.prevent="submit">
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
                        type="url"
                        inputmode="url"
                        placeholder="https://www.youtube.com/watch?v=…"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="lesson-video-title">Descriptive title</Label>
                    <Input
                        id="lesson-video-title"
                        v-model="title"
                        maxlength="255"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="lesson-video-caption">Caption (optional)</Label>
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
                <p v-if="error" role="alert" class="text-sm text-destructive">
                    {{ error }}
                </p>

                <DialogFooter>
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
