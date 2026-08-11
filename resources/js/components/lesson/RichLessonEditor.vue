<script setup lang="ts">
import {
    Bold,
    Code2,
    Heading1,
    Heading2,
    Heading3,
    Image,
    Italic,
    Link2,
    List,
    ListOrdered,
    MessageSquareWarning,
    Minus,
    Pilcrow,
    Quote,
    Redo2,
    Table2,
    Undo2,
    Upload,
    Video,
} from '@lucide/vue';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import LinkExtension from '@tiptap/extension-link';
import { TableKit } from '@tiptap/extension-table';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { common, createLowlight } from 'lowlight';
import { computed, ref, watch } from 'vue';
import LessonImageDialog from '@/components/lesson/dialogs/LessonImageDialog.vue';
import type { LessonImageDialogValue } from '@/components/lesson/dialogs/LessonImageDialog.vue';
import LessonLinkDialog from '@/components/lesson/dialogs/LessonLinkDialog.vue';
import LessonResourceDialog from '@/components/lesson/dialogs/LessonResourceDialog.vue';
import type { LessonResourceDialogValue } from '@/components/lesson/dialogs/LessonResourceDialog.vue';
import LessonVideoDialog from '@/components/lesson/dialogs/LessonVideoDialog.vue';
import {
    ExternalVideo,
    LessonCallout,
    LessonFile,
    LessonImage,
} from '@/lib/lessonEditorExtensions';
import { canonicalLessonDocument } from '@/types/lesson-content';
import type {
    LessonCalloutType,
    LessonCodeLanguage,
    LessonDocument,
    UploadedLessonAsset,
} from '@/types/lesson-content';

const props = defineProps<{
    modelValue: LessonDocument;
    assetUploadUrl: string | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [document: LessonDocument];
}>();

const codeLanguages: LessonCodeLanguage[] = [
    'plain',
    'html',
    'css',
    'javascript',
    'typescript',
    'php',
    'sql',
    'python',
    'cpp',
    'java',
    'json',
    'bash',
];
const calloutTypes: LessonCalloutType[] = [
    'info',
    'tip',
    'warning',
    'important',
];
const lowlight = createLowlight(common);
const selectedLanguage = ref<LessonCodeLanguage>('plain');
const selectedCallout = ref<LessonCalloutType>('info');
const serializedDocument = ref(
    JSON.stringify(canonicalLessonDocument(props.modelValue)),
);
const toolbarError = ref('');
const uploading = ref(false);
const uploadProgress = ref(0);
const dialogError = ref('');

const linkDialogOpen = ref(false);
const linkEditing = ref(false);
const linkInitialUrl = ref('');
const linkInitialText = ref('');

const videoDialogOpen = ref(false);
const videoEditing = ref(false);
const videoInitialUrl = ref('');
const videoInitialTitle = ref('');
const videoInitialCaption = ref('');

const imageDialogOpen = ref(false);
const imageEditing = ref(false);
const imageInitialFile = ref<File | null>(null);
const imageExistingUrl = ref<string | null>(null);
const imageInitialAltText = ref('');
const imageInitialCaption = ref('');
const imageInitialAlignment = ref<'left' | 'center' | 'right'>('center');
const imageInitialSize = ref<'small' | 'medium' | 'large' | 'full'>('large');
const imageInitialDecorative = ref(false);

const resourceDialogOpen = ref(false);
const resourceEditing = ref(false);
const resourceInitialTitle = ref('');
const resourceInitialCaption = ref('');
const resourceOriginalName = ref('');
const resourceMimeType = ref('');
const resourceFileSize = ref(0);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            code: false,
            codeBlock: false,
            heading: { levels: [1, 2, 3] },
            link: false,
            strike: false,
            underline: false,
        }),
        LinkExtension.configure({
            openOnClick: false,
            autolink: false,
            protocols: ['http', 'https'],
            isAllowedUri: isSafeHttpUrl,
            HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer' },
        }),
        TableKit.configure({ table: { resizable: true } }),
        CodeBlockLowlight.configure({
            lowlight,
            defaultLanguage: 'plain',
            enableTabIndentation: true,
            tabSize: 2,
        }),
        LessonImage,
        ExternalVideo,
        LessonCallout,
        LessonFile,
    ],
    editorProps: {
        attributes: {
            class: 'tiptap min-h-[28rem] px-5 py-5 focus:outline-none md:px-7',
            'aria-label': 'Rich lesson content editor',
        },
        handlePaste: (_view, event) => openClipboardImage(event),
        handleDrop: (_view, event) => openDroppedImage(event),
    },
    onUpdate: ({ editor: currentEditor }) => {
        const document = canonicalLessonDocument(
            currentEditor.getJSON() as LessonDocument,
        );
        serializedDocument.value = JSON.stringify(document);
        emit('update:modelValue', document);
    },
});

const assetControlsDisabled = computed(() => uploading.value);

watch(
    () => props.modelValue,
    (document) => {
        if (!editor.value) {
            return;
        }

        const current = canonicalLessonDocument(
            editor.value.getJSON() as LessonDocument,
        );

        if (JSON.stringify(current) !== JSON.stringify(document)) {
            editor.value.commands.setContent(document, { emitUpdate: false });
            serializedDocument.value = JSON.stringify(
                canonicalLessonDocument(document),
            );
        }
    },
    { deep: true },
);

function openLinkDialog(): void {
    if (!editor.value) {
        return;
    }

    linkEditing.value = editor.value.isActive('link');

    if (linkEditing.value) {
        editor.value.chain().focus().extendMarkRange('link').run();
    }

    linkInitialUrl.value = String(
        editor.value.getAttributes('link').href ?? '',
    );
    linkInitialText.value = editor.value.state.doc.textBetween(
        editor.value.state.selection.from,
        editor.value.state.selection.to,
        ' ',
    );
    linkDialogOpen.value = true;
}

function saveLink(value: { url: string; text: string }): void {
    if (!editor.value) {
        return;
    }

    const { from, to } = editor.value.state.selection;
    const selectedText = editor.value.state.doc.textBetween(from, to, ' ');

    if (from === to || selectedText !== value.text) {
        editor.value
            .chain()
            .focus()
            .insertContentAt(
                { from, to },
                {
                    type: 'text',
                    text: value.text,
                    marks: [{ type: 'link', attrs: { href: value.url } }],
                },
            )
            .run();
    } else {
        editor.value.chain().focus().setLink({ href: value.url }).run();
    }

    linkDialogOpen.value = false;
}

function removeLink(): void {
    editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
    linkDialogOpen.value = false;
}

function openVideoDialog(): void {
    const attrs = editor.value?.getAttributes('externalVideo') ?? {};
    videoEditing.value = editor.value?.isActive('externalVideo') ?? false;
    videoInitialUrl.value = String(attrs.url ?? '');
    videoInitialTitle.value = String(attrs.title ?? 'Lesson video');
    videoInitialCaption.value = String(attrs.caption ?? '');
    videoDialogOpen.value = true;
}

function saveVideo(value: {
    url: string;
    title: string;
    caption: string | null;
}): void {
    const attrs = { ...value, provider: null, videoId: null, embedUrl: null };

    if (videoEditing.value) {
        editor.value
            ?.chain()
            .focus()
            .updateAttributes('externalVideo', attrs)
            .run();
    } else {
        editor.value
            ?.chain()
            .focus()
            .insertContent({ type: 'externalVideo', attrs })
            .run();
    }

    videoDialogOpen.value = false;
}

function openImageDialog(file: File | null = null): void {
    toolbarError.value = '';

    if (!props.assetUploadUrl) {
        toolbarError.value =
            'Select a module to prepare a private lesson draft before uploading.';

        return;
    }

    const attrs = editor.value?.getAttributes('lessonImage') ?? {};
    imageEditing.value = editor.value?.isActive('lessonImage') ?? false;
    imageInitialFile.value = file;
    imageExistingUrl.value = typeof attrs.url === 'string' ? attrs.url : null;
    imageInitialAltText.value = String(attrs.altText ?? '');
    imageInitialCaption.value = String(attrs.caption ?? '');
    imageInitialAlignment.value = isImageAlignment(attrs.alignment)
        ? attrs.alignment
        : 'center';
    imageInitialSize.value = isImageSize(attrs.size) ? attrs.size : 'large';
    imageInitialDecorative.value = attrs.decorative === true;
    dialogError.value = '';
    uploadProgress.value = 0;
    imageDialogOpen.value = true;
}

async function saveImage(value: LessonImageDialogValue): Promise<void> {
    const existing = editor.value?.getAttributes('lessonImage') ?? {};
    let asset: UploadedLessonAsset | null = null;

    dialogError.value = '';

    if (value.file) {
        asset = await uploadAsset('image', value.file, {
            alt_text: value.altText || null,
            caption: value.caption,
            decorative: value.decorative,
        });

        if (!asset) {
            return;
        }
    } else if (imageEditing.value) {
        const assetId = Number(existing.lessonAssetId);

        if (
            !(await updateAssetMetadata(assetId, {
                alt_text: value.altText || null,
                caption: value.caption,
                decorative: value.decorative,
            }))
        ) {
            return;
        }
    }

    const attrs = {
        lessonAssetId: asset?.id ?? existing.lessonAssetId,
        altText: value.altText,
        caption: value.caption,
        alignment: value.alignment,
        size: value.size,
        decorative: value.decorative,
        url: asset?.url ?? existing.url,
        originalName: asset?.original_name ?? existing.originalName,
        mimeType: asset?.mime_type ?? existing.mimeType,
        fileSize: asset?.file_size ?? existing.fileSize,
    };

    if (imageEditing.value) {
        editor.value
            ?.chain()
            .focus()
            .updateAttributes('lessonImage', attrs)
            .run();
    } else {
        editor.value
            ?.chain()
            .focus()
            .insertContent({ type: 'lessonImage', attrs })
            .run();
    }

    imageDialogOpen.value = false;
}

function openResourceDialog(): void {
    toolbarError.value = '';

    if (!props.assetUploadUrl) {
        toolbarError.value =
            'Select a module to prepare a private lesson draft before uploading.';

        return;
    }

    const attrs = editor.value?.getAttributes('lessonFile') ?? {};
    resourceEditing.value = editor.value?.isActive('lessonFile') ?? false;
    resourceInitialTitle.value = String(attrs.title ?? '');
    resourceInitialCaption.value = String(attrs.caption ?? '');
    resourceOriginalName.value = String(attrs.originalName ?? '');
    resourceMimeType.value = String(attrs.mimeType ?? 'application/pdf');
    resourceFileSize.value = Number(attrs.fileSize ?? 0);
    dialogError.value = '';
    uploadProgress.value = 0;
    resourceDialogOpen.value = true;
}

async function saveResource(value: LessonResourceDialogValue): Promise<void> {
    const existing = editor.value?.getAttributes('lessonFile') ?? {};
    let asset: UploadedLessonAsset | null = null;

    dialogError.value = '';

    if (value.file) {
        asset = await uploadAsset('document', value.file, {
            caption: value.caption,
        });

        if (!asset) {
            return;
        }
    } else if (resourceEditing.value) {
        if (
            !(await updateAssetMetadata(Number(existing.lessonAssetId), {
                caption: value.caption,
            }))
        ) {
            return;
        }
    }

    const attrs = {
        lessonAssetId: asset?.id ?? existing.lessonAssetId,
        title: value.title,
        caption: value.caption,
        url: asset?.url ?? existing.url,
        downloadUrl: asset?.download_url ?? existing.downloadUrl,
        originalName: asset?.original_name ?? existing.originalName,
        mimeType: asset?.mime_type ?? existing.mimeType,
        fileSize: asset?.file_size ?? existing.fileSize,
    };

    if (resourceEditing.value) {
        editor.value
            ?.chain()
            .focus()
            .updateAttributes('lessonFile', attrs)
            .run();
    } else {
        editor.value
            ?.chain()
            .focus()
            .insertContent({ type: 'lessonFile', attrs })
            .run();
    }

    resourceDialogOpen.value = false;
}

function insertCode(): void {
    editor.value
        ?.chain()
        .focus()
        .setCodeBlock({ language: selectedLanguage.value })
        .run();
}

function insertCallout(): void {
    editor.value
        ?.chain()
        .focus()
        .insertContent({
            type: 'callout',
            attrs: { type: selectedCallout.value },
            content: [
                { type: 'text', text: 'Add an important learning note.' },
            ],
        })
        .run();
}

function openClipboardImage(event: ClipboardEvent): boolean {
    const file = Array.from(event.clipboardData?.files ?? []).find((item) =>
        item.type.startsWith('image/'),
    );

    if (!file) {
        return false;
    }

    event.preventDefault();
    openImageDialog(file);

    return true;
}

function openDroppedImage(event: DragEvent): boolean {
    const file = Array.from(event.dataTransfer?.files ?? []).find((item) =>
        item.type.startsWith('image/'),
    );

    if (!file) {
        return false;
    }

    event.preventDefault();
    const position = editor.value?.view.posAtCoords({
        left: event.clientX,
        top: event.clientY,
    });

    if (position) {
        editor.value?.commands.setTextSelection(position.pos);
    }

    openImageDialog(file);

    return true;
}

function uploadAsset(
    type: 'image' | 'document',
    file: File,
    metadata: {
        alt_text?: string | null;
        caption: string | null;
        decorative?: boolean;
    },
): Promise<UploadedLessonAsset | null> {
    if (!props.assetUploadUrl) {
        return Promise.resolve(null);
    }

    uploading.value = true;
    uploadProgress.value = 0;
    dialogError.value = '';

    return new Promise((resolve) => {
        const request = new XMLHttpRequest();
        const data = new FormData();
        data.append('asset_type', type);
        data.append('file', file);

        if (metadata.alt_text) {
            data.append('alt_text', metadata.alt_text);
        }

        if (metadata.caption) {
            data.append('caption', metadata.caption);
        }

        if (metadata.decorative !== undefined) {
            data.append('decorative', metadata.decorative ? '1' : '0');
        }

        request.open('POST', props.assetUploadUrl!);
        request.responseType = 'json';
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-CSRF-TOKEN', csrfToken());
        request.withCredentials = true;
        request.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                uploadProgress.value = Math.min(
                    99,
                    Math.round((event.loaded / event.total) * 100),
                );
            }
        };
        request.onload = () => {
            const payload = request.response as {
                asset?: UploadedLessonAsset;
                message?: string;
                errors?: Record<string, string[]>;
            } | null;

            if (
                request.status < 200 ||
                request.status >= 300 ||
                !payload?.asset
            ) {
                dialogError.value = responseError(
                    payload,
                    'The asset could not be uploaded.',
                );
                finishUpload();
                resolve(null);

                return;
            }

            uploadProgress.value = 100;
            finishUpload();
            resolve(payload.asset);
        };
        request.onerror = () => {
            dialogError.value =
                'The upload failed. Check your connection and try again.';
            finishUpload();
            resolve(null);
        };
        request.send(data);
    });
}

async function updateAssetMetadata(
    assetId: number,
    metadata: {
        alt_text?: string | null;
        caption: string | null;
        decorative?: boolean;
    },
): Promise<boolean> {
    if (!props.assetUploadUrl || !Number.isInteger(assetId) || assetId < 1) {
        dialogError.value = 'This lesson asset can no longer be edited.';

        return false;
    }

    uploading.value = true;
    uploadProgress.value = 100;
    dialogError.value = '';

    try {
        const response = await fetch(
            `${props.assetUploadUrl.replace(/\/$/, '')}/${assetId}`,
            {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(metadata),
                credentials: 'same-origin',
            },
        );
        const payload = (await response.json()) as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (!response.ok) {
            dialogError.value = responseError(
                payload,
                'The asset metadata could not be updated.',
            );

            return false;
        }

        return true;
    } catch {
        dialogError.value =
            'The update failed. Check your connection and try again.';

        return false;
    } finally {
        finishUpload();
    }
}

function finishUpload(): void {
    uploading.value = false;
}

function responseError(
    payload: { message?: string; errors?: Record<string, string[]> } | null,
    fallback: string,
): string {
    return (
        Object.values(payload?.errors ?? {})[0]?.[0] ??
        payload?.message ??
        fallback
    );
}

function isSafeHttpUrl(value: string): boolean {
    try {
        const parsed = new URL(value);

        return (
            ['http:', 'https:'].includes(parsed.protocol) &&
            !parsed.username &&
            !parsed.password
        );
    } catch {
        return false;
    }
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function isImageAlignment(
    value: unknown,
): value is 'left' | 'center' | 'right' {
    return ['left', 'center', 'right'].includes(String(value));
}

function isImageSize(
    value: unknown,
): value is 'small' | 'medium' | 'large' | 'full' {
    return ['small', 'medium', 'large', 'full'].includes(String(value));
}
</script>

<template>
    <div class="overflow-hidden rounded-lg border bg-background">
        <textarea
            name="content_document"
            :value="serializedDocument"
            class="hidden"
            aria-hidden="true"
            tabindex="-1"
        />
        <div
            class="flex flex-wrap items-center gap-1 border-b bg-muted/35 p-2"
            role="toolbar"
            aria-label="Lesson formatting"
        >
            <button
                type="button"
                class="editor-button"
                :class="{
                    'editor-button-active': editor?.isActive('paragraph'),
                }"
                aria-label="Paragraph"
                @click="editor?.chain().focus().setParagraph().run()"
            >
                <Pilcrow />
            </button>
            <button
                v-for="level in [1, 2, 3] as const"
                :key="level"
                type="button"
                class="editor-button"
                :class="{
                    'editor-button-active': editor?.isActive('heading', {
                        level,
                    }),
                }"
                :aria-label="`Heading ${level}`"
                @click="editor?.chain().focus().toggleHeading({ level }).run()"
            >
                <Heading1 v-if="level === 1" />
                <Heading2 v-else-if="level === 2" />
                <Heading3 v-else />
            </button>
            <span class="editor-separator" />
            <button
                type="button"
                class="editor-button"
                :class="{ 'editor-button-active': editor?.isActive('bold') }"
                aria-label="Bold"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                <Bold />
            </button>
            <button
                type="button"
                class="editor-button"
                :class="{ 'editor-button-active': editor?.isActive('italic') }"
                aria-label="Italic"
                @click="editor?.chain().focus().toggleItalic().run()"
            >
                <Italic />
            </button>
            <button
                type="button"
                class="editor-button"
                :class="{ 'editor-button-active': editor?.isActive('link') }"
                aria-label="Add or edit link"
                @click="openLinkDialog"
            >
                <Link2 />
            </button>
            <span class="editor-separator" />
            <button
                type="button"
                class="editor-button"
                :class="{
                    'editor-button-active': editor?.isActive('bulletList'),
                }"
                aria-label="Bullet list"
                @click="editor?.chain().focus().toggleBulletList().run()"
            >
                <List />
            </button>
            <button
                type="button"
                class="editor-button"
                :class="{
                    'editor-button-active': editor?.isActive('orderedList'),
                }"
                aria-label="Numbered list"
                @click="editor?.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered />
            </button>
            <button
                type="button"
                class="editor-button"
                :class="{
                    'editor-button-active': editor?.isActive('blockquote'),
                }"
                aria-label="Block quote"
                @click="editor?.chain().focus().toggleBlockquote().run()"
            >
                <Quote />
            </button>
            <button
                type="button"
                class="editor-button"
                aria-label="Insert table"
                @click="
                    editor
                        ?.chain()
                        .focus()
                        .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
                        .run()
                "
            >
                <Table2 />
            </button>
            <button
                type="button"
                class="editor-button"
                aria-label="Insert divider"
                @click="editor?.chain().focus().setHorizontalRule().run()"
            >
                <Minus />
            </button>
            <span class="editor-separator" />
            <button
                type="button"
                class="editor-button-with-label"
                :class="{
                    'editor-button-active': editor?.isActive('lessonImage'),
                }"
                :disabled="assetControlsDisabled"
                @click="openImageDialog()"
            >
                <Image />
                {{ editor?.isActive('lessonImage') ? 'Edit image' : 'Image' }}
            </button>
            <button
                type="button"
                class="editor-button-with-label"
                :class="{
                    'editor-button-active': editor?.isActive('externalVideo'),
                }"
                @click="openVideoDialog"
            >
                <Video />
                {{ editor?.isActive('externalVideo') ? 'Edit video' : 'Video' }}
            </button>
            <button
                type="button"
                class="editor-button-with-label"
                :class="{
                    'editor-button-active': editor?.isActive('lessonFile'),
                }"
                :disabled="assetControlsDisabled"
                @click="openResourceDialog"
            >
                <Upload />
                {{ editor?.isActive('lessonFile') ? 'Edit PDF' : 'PDF' }}
            </button>
            <span class="editor-separator" />
            <select
                v-model="selectedLanguage"
                class="editor-select"
                aria-label="Code language"
            >
                <option v-for="language in codeLanguages" :key="language">
                    {{ language }}
                </option>
            </select>
            <button
                type="button"
                class="editor-button"
                aria-label="Insert code block"
                @click="insertCode"
            >
                <Code2 />
            </button>
            <select
                v-model="selectedCallout"
                class="editor-select"
                aria-label="Callout type"
            >
                <option v-for="type in calloutTypes" :key="type">
                    {{ type }}
                </option>
            </select>
            <button
                type="button"
                class="editor-button"
                aria-label="Insert callout"
                @click="insertCallout"
            >
                <MessageSquareWarning />
            </button>
            <span class="editor-separator" />
            <button
                type="button"
                class="editor-button"
                :disabled="!editor?.can().chain().focus().undo().run()"
                aria-label="Undo"
                @click="editor?.chain().focus().undo().run()"
            >
                <Undo2 />
            </button>
            <button
                type="button"
                class="editor-button"
                :disabled="!editor?.can().chain().focus().redo().run()"
                aria-label="Redo"
                @click="editor?.chain().focus().redo().run()"
            >
                <Redo2 />
            </button>
        </div>

        <p
            v-if="!assetUploadUrl"
            class="border-b bg-amber-50 px-4 py-2 text-xs text-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
        >
            Select a module to enable private image and PDF uploads. Your draft
            will be prepared automatically.
        </p>
        <p
            v-if="toolbarError"
            role="alert"
            class="border-b bg-destructive/10 px-4 py-2 text-sm text-destructive"
        >
            {{ toolbarError }}
        </p>

        <EditorContent :editor="editor" class="rich-lesson-editor" />
        <p class="border-t bg-muted/20 px-4 py-2 text-xs text-muted-foreground">
            Tip: paste an image from the clipboard or drag a JPG, PNG, or WebP
            image into the editor.
        </p>

        <LessonLinkDialog
            :open="linkDialogOpen"
            :editing="linkEditing"
            :initial-url="linkInitialUrl"
            :initial-text="linkInitialText"
            @update:open="linkDialogOpen = $event"
            @save="saveLink"
            @remove="removeLink"
        />
        <LessonVideoDialog
            :open="videoDialogOpen"
            :editing="videoEditing"
            :initial-url="videoInitialUrl"
            :initial-title="videoInitialTitle"
            :initial-caption="videoInitialCaption"
            @update:open="videoDialogOpen = $event"
            @save="saveVideo"
        />
        <LessonImageDialog
            :open="imageDialogOpen"
            :editing="imageEditing"
            :initial-file="imageInitialFile"
            :existing-url="imageExistingUrl"
            :initial-alt-text="imageInitialAltText"
            :initial-caption="imageInitialCaption"
            :initial-alignment="imageInitialAlignment"
            :initial-size="imageInitialSize"
            :initial-decorative="imageInitialDecorative"
            :busy="uploading"
            :progress="uploadProgress"
            :server-error="dialogError"
            @update:open="imageDialogOpen = $event"
            @save="saveImage"
        />
        <LessonResourceDialog
            :open="resourceDialogOpen"
            :editing="resourceEditing"
            :initial-title="resourceInitialTitle"
            :initial-caption="resourceInitialCaption"
            :original-name="resourceOriginalName"
            :mime-type="resourceMimeType"
            :file-size="resourceFileSize"
            :busy="uploading"
            :progress="uploadProgress"
            :server-error="dialogError"
            @update:open="resourceDialogOpen = $event"
            @save="saveResource"
        />
    </div>
</template>
