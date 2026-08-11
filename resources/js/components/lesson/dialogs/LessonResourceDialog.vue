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
import { Textarea } from '@/components/ui/textarea';

export type LessonResourceDialogValue = {
    file: File | null;
    title: string;
    caption: string | null;
};

const props = defineProps<{
    open: boolean;
    editing: boolean;
    initialTitle: string;
    initialCaption: string;
    originalName: string;
    mimeType: string;
    fileSize: number;
    busy: boolean;
    progress: number;
    serverError: string;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
    save: [value: LessonResourceDialogValue];
}>();

const file = ref<File | null>(null);
const title = ref('');
const caption = ref('');
const clientError = ref('');

watch(
    () => props.open,
    (open) => {
        if (open) {
            file.value = null;
            title.value = props.initialTitle;
            caption.value = props.initialCaption;
            clientError.value = '';
        }
    },
);

function selectFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const selected = input.files?.[0] ?? null;

    if (!selected) {
        return;
    }

    const error = validatePdf(selected);

    if (error) {
        clientError.value = error;
        input.value = '';

        return;
    }

    file.value = selected;
    clientError.value = '';

    if (!title.value.trim()) {
        title.value = selected.name
            .replace(/\.pdf$/i, '')
            .replace(/[-_]+/g, ' ');
    }
}

function submit(): void {
    if (!props.editing && !file.value) {
        clientError.value = 'Choose a PDF resource to upload.';

        return;
    }

    if (file.value) {
        const error = validatePdf(file.value);

        if (error) {
            clientError.value = error;

            return;
        }
    }

    if (!title.value.trim()) {
        clientError.value = 'A clear resource title is required.';

        return;
    }

    emit('save', {
        file: file.value,
        title: title.value.trim(),
        caption: caption.value.trim() || null,
    });
}

function validatePdf(selected: File): string | null {
    if (
        selected.type !== 'application/pdf' &&
        !selected.name.toLowerCase().endsWith('.pdf')
    ) {
        return 'Only PDF resources are supported.';
    }

    return selected.size > 20 * 1024 * 1024
        ? 'PDF resources must be 20 MB or smaller.'
        : null;
}

function formatBytes(bytes: number): string {
    if (!bytes) {
        return 'Size unavailable';
    }

    return bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(2)} MB`
        : `${Math.ceil(bytes / 1024)} KB`;
}
</script>

<template>
    <Dialog :open="open" @update:open="!busy && emit('update:open', $event)">
        <DialogContent :show-close-button="!busy">
            <form class="space-y-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Edit resource' : 'Upload PDF resource'
                    }}</DialogTitle>
                    <DialogDescription>
                        Give learners a clear title instead of relying on the
                        uploaded filename.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="lesson-resource-file">
                        {{ editing ? 'Replace PDF (optional)' : 'PDF file' }}
                    </Label>
                    <Input
                        id="lesson-resource-file"
                        type="file"
                        accept="application/pdf,.pdf"
                        :disabled="busy"
                        @change="selectFile"
                    />
                </div>

                <div
                    v-if="file || originalName"
                    class="rounded-lg border bg-muted/30 p-3 text-sm"
                >
                    <p class="font-medium">{{ file?.name || originalName }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ file?.type || mimeType || 'application/pdf' }} ·
                        {{ formatBytes(file?.size || fileSize) }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="lesson-resource-title">Resource title</Label>
                    <Input
                        id="lesson-resource-title"
                        v-model="title"
                        maxlength="255"
                        :disabled="busy"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="lesson-resource-caption"
                        >Description (optional)</Label
                    >
                    <Textarea
                        id="lesson-resource-caption"
                        v-model="caption"
                        maxlength="2000"
                        :disabled="busy"
                    />
                </div>

                <div v-if="busy" class="space-y-2" aria-live="polite">
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full bg-primary transition-[width]"
                            :style="{ width: `${progress}%` }"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Uploading resource… {{ progress }}%
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
                                  ? 'Update resource'
                                  : 'Insert resource'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
