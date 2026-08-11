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
const imageInput = ref<HTMLInputElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const uploadError = ref('');
const selectedLanguage = ref<LessonCodeLanguage>('plain');
const selectedCallout = ref<LessonCalloutType>('info');
const serializedDocument = ref(
    JSON.stringify(canonicalLessonDocument(props.modelValue)),
);

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
            isAllowedUri: (url) => isSafeHttpUrl(url),
            HTMLAttributes: {
                target: '_blank',
                rel: 'noopener noreferrer',
            },
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
    },
    onUpdate: ({ editor: currentEditor }) => {
        const document = canonicalLessonDocument(
            currentEditor.getJSON() as LessonDocument,
        );
        serializedDocument.value = JSON.stringify(document);
        emit('update:modelValue', document);
    },
});

const assetControlsDisabled = computed(
    () => props.assetUploadUrl === null || uploading.value,
);

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

function setLink(): void {
    if (!editor.value) {
        return;
    }

    const previous = editor.value.getAttributes('link').href as
        string | undefined;
    const href = window.prompt('Paste an HTTP or HTTPS link', previous ?? '');

    if (href === null) {
        return;
    }

    if (href.trim() === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();

        return;
    }

    if (!isSafeHttpUrl(href)) {
        uploadError.value = 'Links must begin with http:// or https://.';

        return;
    }

    uploadError.value = '';
    editor.value
        .chain()
        .focus()
        .extendMarkRange('link')
        .setLink({ href })
        .run();
}

function insertVideo(): void {
    const url = window.prompt('YouTube or Vimeo URL');

    if (!url) {
        return;
    }

    const title = window.prompt('Descriptive video title', 'Lesson video');

    if (!title?.trim()) {
        return;
    }

    editor.value
        ?.chain()
        .focus()
        .insertContent({
            type: 'externalVideo',
            attrs: { url, title: title.trim(), caption: null },
        })
        .run();
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

function chooseAsset(kind: 'image' | 'document'): void {
    uploadError.value = '';

    if (props.assetUploadUrl === null) {
        uploadError.value =
            'Save the lesson once before inserting private images or PDFs.';

        return;
    }

    (kind === 'image' ? imageInput.value : fileInput.value)?.click();
}

async function chooseOrEditAsset(kind: 'image' | 'document'): Promise<void> {
    const nodeName = kind === 'image' ? 'lessonImage' : 'lessonFile';

    if (!editor.value?.isActive(nodeName)) {
        chooseAsset(kind);

        return;
    }

    const attributes = editor.value.getAttributes(nodeName) as Record<
        string,
        unknown
    >;
    const assetId = Number(attributes.lessonAssetId);

    if (!Number.isInteger(assetId) || assetId < 1) {
        uploadError.value = 'This lesson asset cannot be edited.';

        return;
    }

    if (kind === 'image') {
        const altText = window.prompt(
            'Describe this image for learners',
            String(attributes.altText ?? ''),
        );

        if (!altText?.trim()) {
            uploadError.value = 'Image alt text is required.';

            return;
        }

        const caption = window.prompt(
            'Optional image caption',
            String(attributes.caption ?? ''),
        );

        if (caption === null) {
            return;
        }

        if (
            !(await updateAssetMetadata(assetId, {
                alt_text: altText.trim(),
                caption: caption.trim() || null,
            }))
        ) {
            return;
        }

        editor.value
            .chain()
            .focus()
            .updateAttributes('lessonImage', {
                altText: altText.trim(),
                caption: caption.trim() || null,
            })
            .run();

        return;
    }

    const title = window.prompt(
        'Resource title',
        String(attributes.title ?? ''),
    );

    if (!title?.trim()) {
        return;
    }

    const caption = window.prompt(
        'Optional resource description',
        String(attributes.caption ?? ''),
    );

    if (caption === null) {
        return;
    }

    if (
        !(await updateAssetMetadata(assetId, {
            caption: caption.trim() || null,
        }))
    ) {
        return;
    }

    editor.value
        .chain()
        .focus()
        .updateAttributes('lessonFile', {
            title: title.trim(),
            caption: caption.trim() || null,
        })
        .run();
}

async function uploadImage(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    const altText = window.prompt('Describe this image for learners');

    if (!altText?.trim()) {
        uploadError.value = 'Image alt text is required.';

        return;
    }

    const caption = window.prompt('Optional image caption', '') ?? '';
    const asset = await uploadAsset('image', file, altText.trim(), caption);

    if (!asset) {
        return;
    }

    editor.value
        ?.chain()
        .focus()
        .insertContent({
            type: 'lessonImage',
            attrs: {
                lessonAssetId: asset.id,
                altText: asset.alt_text,
                caption: asset.caption,
                alignment: 'center',
                size: 'large',
                decorative: false,
                url: asset.url,
                originalName: asset.original_name,
                mimeType: asset.mime_type,
                fileSize: asset.file_size,
            },
        })
        .run();
}

async function uploadDocument(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    const title = window.prompt('Resource title', file.name);

    if (!title?.trim()) {
        return;
    }

    const caption = window.prompt('Optional resource description', '') ?? '';
    const asset = await uploadAsset('document', file, null, caption);

    if (!asset) {
        return;
    }

    editor.value
        ?.chain()
        .focus()
        .insertContent({
            type: 'lessonFile',
            attrs: {
                lessonAssetId: asset.id,
                title: title.trim(),
                caption: asset.caption,
                url: asset.url,
                downloadUrl: asset.download_url,
                originalName: asset.original_name,
                mimeType: asset.mime_type,
                fileSize: asset.file_size,
            },
        })
        .run();
}

async function uploadAsset(
    type: 'image' | 'document',
    file: File,
    altText: string | null,
    caption: string,
): Promise<UploadedLessonAsset | null> {
    if (!props.assetUploadUrl) {
        return null;
    }

    uploading.value = true;
    uploadError.value = '';
    const data = new FormData();
    data.append('asset_type', type);
    data.append('file', file);

    if (altText !== null) {
        data.append('alt_text', altText);
    }

    if (caption.trim()) {
        data.append('caption', caption.trim());
    }

    try {
        const response = await fetch(props.assetUploadUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: data,
            credentials: 'same-origin',
        });
        const payload = (await response.json()) as {
            asset?: UploadedLessonAsset;
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (!response.ok || !payload.asset) {
            uploadError.value =
                Object.values(payload.errors ?? {})[0]?.[0] ??
                payload.message ??
                'The asset could not be uploaded.';

            return null;
        }

        return payload.asset;
    } catch {
        uploadError.value = 'The asset upload failed. Please try again.';

        return null;
    } finally {
        uploading.value = false;
    }
}

async function updateAssetMetadata(
    assetId: number,
    metadata: { alt_text?: string; caption: string | null },
): Promise<boolean> {
    if (!props.assetUploadUrl) {
        return false;
    }

    uploading.value = true;
    uploadError.value = '';

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
            uploadError.value =
                Object.values(payload.errors ?? {})[0]?.[0] ??
                payload.message ??
                'The asset metadata could not be updated.';

            return false;
        }

        return true;
    } catch {
        uploadError.value =
            'The asset metadata update failed. Please try again.';

        return false;
    } finally {
        uploading.value = false;
    }
}

function isSafeHttpUrl(value: string): boolean {
    try {
        return ['http:', 'https:'].includes(new URL(value).protocol);
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
                @click="setLink"
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
                :aria-label="
                    editor?.isActive('lessonImage')
                        ? 'Edit selected image metadata'
                        : 'Insert image'
                "
                @click="chooseOrEditAsset('image')"
            >
                <Image />
                {{ editor?.isActive('lessonImage') ? 'Edit image' : 'Image' }}
            </button>
            <button
                type="button"
                class="editor-button-with-label"
                @click="insertVideo"
            >
                <Video /> Video
            </button>
            <button
                type="button"
                class="editor-button-with-label"
                :class="{
                    'editor-button-active': editor?.isActive('lessonFile'),
                }"
                :disabled="assetControlsDisabled"
                :aria-label="
                    editor?.isActive('lessonFile')
                        ? 'Edit selected PDF metadata'
                        : 'Insert PDF'
                "
                @click="chooseOrEditAsset('document')"
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
            Save the lesson first to unlock private image and PDF uploads. All
            other content can be authored now.
        </p>
        <p
            v-if="uploading"
            class="border-b bg-muted px-4 py-2 text-xs text-muted-foreground"
        >
            Uploading private lesson asset…
        </p>
        <p
            v-if="uploadError"
            role="alert"
            class="border-b bg-destructive/10 px-4 py-2 text-sm text-destructive"
        >
            {{ uploadError }}
        </p>

        <EditorContent :editor="editor" class="rich-lesson-editor" />

        <input
            ref="imageInput"
            class="hidden"
            type="file"
            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
            @change="uploadImage"
        />
        <input
            ref="fileInput"
            class="hidden"
            type="file"
            accept="application/pdf,.pdf"
            @change="uploadDocument"
        />
    </div>
</template>
