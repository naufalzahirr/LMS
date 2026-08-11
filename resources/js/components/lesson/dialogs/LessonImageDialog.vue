<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
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

export type LessonImageDialogValue = {
    file: File | null;
    altText: string;
    caption: string | null;
    alignment: 'left' | 'center' | 'right';
    size: 'small' | 'medium' | 'large' | 'full';
    decorative: boolean;
};

const props = defineProps<{
    open: boolean;
    editing: boolean;
    initialFile: File | null;
    existingUrl: string | null;
    initialAltText: string;
    initialCaption: string;
    initialAlignment: 'left' | 'center' | 'right';
    initialSize: 'small' | 'medium' | 'large' | 'full';
    initialDecorative: boolean;
    busy: boolean;
    progress: number;
    serverError: string;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
    save: [value: LessonImageDialogValue];
}>();

const file = ref<File | null>(null);
const altText = ref('');
const caption = ref('');
const alignment = ref<'left' | 'center' | 'right'>('center');
const size = ref<'small' | 'medium' | 'large' | 'full'>('large');
const decorative = ref(false);
const clientError = ref('');
const previewUrl = ref<string | null>(null);
let objectUrl: string | null = null;

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        file.value = props.initialFile;
        altText.value = props.initialAltText;
        caption.value = props.initialCaption;
        alignment.value = props.initialAlignment;
        size.value = props.initialSize;
        decorative.value = props.initialDecorative;
        clientError.value = '';
        setPreview(props.initialFile, props.existingUrl);
    },
);

onBeforeUnmount(revokeObjectUrl);

function selectFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const selected = input.files?.[0] ?? null;

    if (!selected) {
        return;
    }

    const error = validateImage(selected);

    if (error) {
        clientError.value = error;
        input.value = '';

        return;
    }

    file.value = selected;
    clientError.value = '';
    setPreview(selected, props.existingUrl);
}

function submit(): void {
    if (!props.editing && !file.value) {
        clientError.value = 'Choose an image to upload.';

        return;
    }

    if (file.value) {
        const error = validateImage(file.value);

        if (error) {
            clientError.value = error;

            return;
        }
    }

    if (!decorative.value && !altText.value.trim()) {
        clientError.value =
            'Alt text is required unless the image is decorative.';

        return;
    }

    emit('save', {
        file: file.value,
        altText: decorative.value ? '' : altText.value.trim(),
        caption: caption.value.trim() || null,
        alignment: alignment.value,
        size: size.value,
        decorative: decorative.value,
    });
}

function validateImage(selected: File): string | null {
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(selected.type)) {
        return 'Choose a JPG, PNG, or WebP image.';
    }

    return selected.size > 10 * 1024 * 1024
        ? 'Images must be 10 MB or smaller.'
        : null;
}

function setPreview(selected: File | null, existing: string | null): void {
    revokeObjectUrl();

    if (selected) {
        objectUrl = URL.createObjectURL(selected);
        previewUrl.value = objectUrl;
    } else {
        previewUrl.value = existing;
    }
}

function revokeObjectUrl(): void {
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="!busy && emit('update:open', $event)">
        <DialogContent
            class="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
            :show-close-button="!busy"
        >
            <form class="space-y-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Edit image' : 'Upload image'
                    }}</DialogTitle>
                    <DialogDescription>
                        Add an accessible image and choose how it appears in the
                        lesson.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="lesson-image-file">
                        {{
                            editing ? 'Replace image (optional)' : 'Image file'
                        }}
                    </Label>
                    <Input
                        id="lesson-image-file"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                        :disabled="busy"
                        @change="selectFile"
                    />
                    <p v-if="file" class="text-xs text-muted-foreground">
                        {{ file.name }} ·
                        {{ (file.size / 1024 / 1024).toFixed(2) }} MB
                    </p>
                </div>

                <img
                    v-if="previewUrl"
                    :src="previewUrl"
                    :alt="decorative ? '' : altText || 'Selected image preview'"
                    class="max-h-64 w-full rounded-lg border bg-muted object-contain"
                />

                <label
                    class="flex items-start gap-3 rounded-lg border p-3 text-sm"
                >
                    <input
                        v-model="decorative"
                        type="checkbox"
                        class="mt-0.5 size-4"
                        :disabled="busy"
                    />
                    <span>
                        <span class="font-medium">Decorative image</span>
                        <span class="mt-0.5 block text-muted-foreground">
                            Use only when the image adds no information for
                            learners.
                        </span>
                    </span>
                </label>

                <div class="grid gap-2">
                    <Label for="lesson-image-alt">Alt text</Label>
                    <Input
                        id="lesson-image-alt"
                        v-model="altText"
                        maxlength="500"
                        :disabled="decorative || busy"
                        placeholder="Describe the learning information in the image"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="lesson-image-caption">Caption (optional)</Label>
                    <Textarea
                        id="lesson-image-caption"
                        v-model="caption"
                        maxlength="2000"
                        :disabled="busy"
                    />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="lesson-image-alignment">Alignment</Label>
                        <select
                            id="lesson-image-alignment"
                            v-model="alignment"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                            :disabled="busy"
                        >
                            <option value="left">Left</option>
                            <option value="center">Center</option>
                            <option value="right">Right</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="lesson-image-size">Display size</Label>
                        <select
                            id="lesson-image-size"
                            v-model="size"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                            :disabled="busy"
                        >
                            <option value="small">Small</option>
                            <option value="medium">Medium</option>
                            <option value="large">Large</option>
                            <option value="full">Full width</option>
                        </select>
                    </div>
                </div>

                <div v-if="busy" class="space-y-2" aria-live="polite">
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full bg-primary transition-[width]"
                            :style="{ width: `${progress}%` }"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Uploading image… {{ progress }}%
                    </p>
                </div>
                <p
                    v-if="clientError || serverError"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ clientError || serverError }}
                </p>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="busy"
                        @click="emit('update:open', false)"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="busy">
                        {{
                            busy
                                ? 'Uploading…'
                                : editing
                                  ? 'Update image'
                                  : 'Insert image'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
