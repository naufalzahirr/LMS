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
import { getMarkRange, isNodeSelection } from '@tiptap/core';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import LinkExtension from '@tiptap/extension-link';
import { TableKit } from '@tiptap/extension-table';
import { TextSelection } from '@tiptap/pm/state';
import type { EditorView } from '@tiptap/pm/view';
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
import type { EnsureLessonAssetUpload } from '@/types/lesson-authoring';
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
    canPrepareAssetUpload?: boolean;
    ensureAssetUploadUrl?: EnsureLessonAssetUpload;
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
const editableTextTargetSelector = [
    'p',
    'h1',
    'h2',
    'h3',
    'li',
    'blockquote',
    'pre',
    'th',
    'td',
    'aside[data-lesson-callout] > div',
].join(',');
const lowlight = createLowlight(common);
const selectedLanguage = ref<LessonCodeLanguage>('plain');
const selectedCallout = ref<LessonCalloutType>('info');
const serializedDocument = ref(
    JSON.stringify(canonicalLessonDocument(props.modelValue)),
);
const toolbarError = ref('');
const uploading = ref(false);
const preparingAssetEndpoint = ref(false);
const uploadProgress = ref(0);
const dialogError = ref('');
let assetEndpointPreparation: Promise<string | null> | null = null;

const linkDialogOpen = ref(false);
const linkEditing = ref(false);
const linkInitialUrl = ref('');
const linkInitialText = ref('');
const linkSelection = ref<{ from: number; to: number } | null>(null);

const videoDialogOpen = ref(false);
const videoEditing = ref(false);
const videoSelectionPosition = ref<number | null>(null);
const videoInsertionPosition = ref<number | null>(null);
const videoInitialUrl = ref('');
const videoInitialTitle = ref('');
const videoInitialCaption = ref('');

const imageDialogOpen = ref(false);
const imageEditing = ref(false);
const imageSelectionPosition = ref<number | null>(null);
const imageInsertionPosition = ref<number | null>(null);
const imageInitialFile = ref<File | null>(null);
const imageExistingUrl = ref<string | null>(null);
const imageInitialAltText = ref('');
const imageInitialCaption = ref('');
const imageInitialAlignment = ref<'left' | 'center' | 'right'>('center');
const imageInitialSize = ref<'small' | 'medium' | 'large' | 'full'>('large');
const imageInitialDecorative = ref(false);

const resourceDialogOpen = ref(false);
const resourceEditing = ref(false);
const resourceSelectionPosition = ref<number | null>(null);
const resourceInsertionPosition = ref<number | null>(null);
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
        handleDOMEvents: {
            click: (view, event) =>
                syncTextSelectionAfterAtomClick(view, event),
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

const assetControlsDisabled = computed(
    () => uploading.value || preparingAssetEndpoint.value,
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

function openLinkDialog(): void {
    if (!editor.value) {
        return;
    }

    const existingLink = selectedLink();
    linkEditing.value = existingLink !== null;
    linkSelection.value = existingLink?.range ?? {
        from: editor.value.state.selection.from,
        to: editor.value.state.selection.to,
    };
    linkInitialUrl.value = existingLink?.href ?? '';
    linkInitialText.value = editor.value.state.doc.textBetween(
        linkSelection.value.from,
        linkSelection.value.to,
        ' ',
    );
    linkDialogOpen.value = true;
}

function selectedLink(): {
    href: string;
    range: { from: number; to: number };
} | null {
    const currentEditor = editor.value;

    if (!currentEditor) {
        return null;
    }

    currentEditor.chain().focus().extendMarkRange('link').run();
    const activeHref = currentEditor.getAttributes('link').href;

    if (typeof activeHref === 'string' && activeHref !== '') {
        return {
            href: activeHref,
            range: {
                from: currentEditor.state.selection.from,
                to: currentEditor.state.selection.to,
            },
        };
    }

    const { doc, schema, selection } = currentEditor.state;
    const linkType = schema.marks.link;
    const positions = [
        selection.from,
        selection.from - 1,
        selection.from + 1,
    ].filter((position) => position >= 0 && position <= doc.content.size);

    for (const position of positions) {
        const resolved = doc.resolve(position);
        const mark = resolved.marks().find((item) => item.type === linkType);
        const range = mark
            ? getMarkRange(resolved, linkType, mark.attrs)
            : undefined;

        if (mark && range && typeof mark.attrs.href === 'string') {
            return {
                href: mark.attrs.href,
                range: { from: range.from, to: range.to },
            };
        }
    }

    return null;
}

function saveLink(value: { url: string; text: string }): void {
    if (!editor.value || !linkSelection.value) {
        return;
    }

    const { from, to } = linkSelection.value;
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
        editor.value
            .chain()
            .focus()
            .setTextSelection({ from, to })
            .setLink({ href: value.url })
            .setTextSelection(to)
            .run();
    }

    linkDialogOpen.value = false;
}

function removeLink(): void {
    if (editor.value && linkSelection.value) {
        editor.value
            .chain()
            .focus()
            .setTextSelection(linkSelection.value)
            .unsetLink()
            .setTextSelection(linkSelection.value.to)
            .run();
    }

    linkDialogOpen.value = false;
}

function openVideoDialog(): void {
    videoSelectionPosition.value = selectedNodePosition('externalVideo');
    videoEditing.value = videoSelectionPosition.value !== null;
    videoInsertionPosition.value = videoEditing.value
        ? null
        : currentBlockInsertionPosition();
    const attrs = nodeAttributesAt(
        'externalVideo',
        videoSelectionPosition.value,
    );
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
        if (
            !restoreNodeSelection('externalVideo', videoSelectionPosition.value)
        ) {
            toolbarError.value =
                'The selected video changed. Select it again before editing.';

            return;
        }

        editor.value
            ?.chain()
            .focus()
            .updateAttributes('externalVideo', attrs)
            .run();
    } else {
        insertBlockAt(videoInsertionPosition.value, {
            type: 'externalVideo',
            attrs,
        });
    }

    videoDialogOpen.value = false;
}

async function openImageDialog(file: File | null = null): Promise<void> {
    toolbarError.value = '';
    imageSelectionPosition.value = selectedNodePosition('lessonImage');
    imageInsertionPosition.value =
        imageSelectionPosition.value === null
            ? currentBlockInsertionPosition()
            : null;

    if (!(await resolveAssetUploadUrl())) {
        return;
    }

    const attrs = nodeAttributesAt('lessonImage', imageSelectionPosition.value);
    imageEditing.value = imageSelectionPosition.value !== null;
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
    const existing = nodeAttributesAt(
        'lessonImage',
        imageSelectionPosition.value,
    );
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
        if (
            !restoreNodeSelection('lessonImage', imageSelectionPosition.value)
        ) {
            dialogError.value =
                'The selected image changed. Select it again before editing.';

            return;
        }

        editor.value
            ?.chain()
            .focus()
            .updateAttributes('lessonImage', attrs)
            .run();
    } else {
        insertBlockAt(imageInsertionPosition.value, {
            type: 'lessonImage',
            attrs,
        });
    }

    imageDialogOpen.value = false;
}

async function openResourceDialog(): Promise<void> {
    toolbarError.value = '';
    resourceSelectionPosition.value = selectedNodePosition('lessonFile');
    resourceInsertionPosition.value =
        resourceSelectionPosition.value === null
            ? currentBlockInsertionPosition()
            : null;

    if (!(await resolveAssetUploadUrl())) {
        return;
    }

    const attrs = nodeAttributesAt(
        'lessonFile',
        resourceSelectionPosition.value,
    );
    resourceEditing.value = resourceSelectionPosition.value !== null;
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
    const existing = nodeAttributesAt(
        'lessonFile',
        resourceSelectionPosition.value,
    );
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
        if (
            !restoreNodeSelection('lessonFile', resourceSelectionPosition.value)
        ) {
            dialogError.value =
                'The selected resource changed. Select it again before editing.';

            return;
        }

        editor.value
            ?.chain()
            .focus()
            .updateAttributes('lessonFile', attrs)
            .run();
    } else {
        insertBlockAt(resourceInsertionPosition.value, {
            type: 'lessonFile',
            attrs,
        });
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

function preserveToolbarSelection(event: MouseEvent): void {
    if ((event.target as Element).closest('button')) {
        event.preventDefault();
    }
}

function insertCallout(): void {
    const currentEditor = editor.value;

    if (!currentEditor) {
        return;
    }

    const placeholder = 'Add an important learning note.';
    const insertionPosition = currentEditor.state.selection.from;
    currentEditor
        .chain()
        .focus()
        .insertContent({
            type: 'callout',
            attrs: { type: selectedCallout.value },
            content: [{ type: 'text', text: placeholder }],
        })
        .run();

    let calloutPosition: number | null = null;
    let closestDistance = Number.POSITIVE_INFINITY;

    currentEditor.state.doc.descendants((node, position) => {
        if (node.type.name !== 'callout' || node.textContent !== placeholder) {
            return;
        }

        const distance = Math.abs(position - insertionPosition);

        if (distance < closestDistance) {
            calloutPosition = position;
            closestDistance = distance;
        }
    });

    if (calloutPosition !== null) {
        currentEditor
            .chain()
            .focus()
            .setTextSelection({
                from: calloutPosition + 1,
                to: calloutPosition + 1 + placeholder.length,
            })
            .run();
    }
}

function selectedNodePosition(type: string): number | null {
    const currentEditor = editor.value;

    if (!currentEditor || !isNodeSelection(currentEditor.state.selection)) {
        return null;
    }

    return currentEditor.state.selection.node.type.name === type
        ? currentEditor.state.selection.from
        : null;
}

function nodeAttributesAt(
    type: string,
    position: number | null,
): Record<string, unknown> {
    if (!editor.value || position === null) {
        return {};
    }

    const node = editor.value.state.doc.nodeAt(position);

    return node?.type.name === type ? node.attrs : {};
}

function restoreNodeSelection(type: string, position: number | null): boolean {
    if (!editor.value || position === null) {
        return false;
    }

    const node = editor.value.state.doc.nodeAt(position);

    if (node?.type.name !== type) {
        return false;
    }

    return editor.value.chain().focus().setNodeSelection(position).run();
}

function currentBlockInsertionPosition(): number | null {
    const currentEditor = editor.value;

    if (!currentEditor) {
        return null;
    }

    const { selection } = currentEditor.state;

    return selection.$to.depth >= 1 ? selection.$to.after(1) : selection.to;
}

function insertBlockAt(
    position: number | null,
    node: { type: string; attrs: Record<string, unknown> },
): void {
    const currentEditor = editor.value;

    if (!currentEditor) {
        return;
    }

    const insertionPosition = Math.min(
        position ?? currentEditor.state.selection.to,
        currentEditor.state.doc.content.size,
    );
    currentEditor
        .chain()
        .focus()
        .insertContentAt(insertionPosition, node)
        .run();
}

function syncTextSelectionAfterAtomClick(
    view: EditorView,
    event: MouseEvent,
): boolean {
    if (!isNodeSelection(view.state.selection)) {
        return false;
    }

    const clickedElement =
        event.target instanceof Element ? event.target : null;
    const clickedContainer = clickedElement?.closest<HTMLElement>(
        editableTextTargetSelector,
    );

    if (!clickedContainer || !view.dom.contains(clickedContainer)) {
        return false;
    }

    const textBlock = ['LI', 'TH', 'TD'].includes(clickedContainer.tagName)
        ? (clickedContainer.querySelector<HTMLElement>(
              'p, h1, h2, h3, blockquote, pre',
          ) ?? clickedContainer)
        : clickedContainer;
    let position: number | null = null;
    const domSelection = view.dom.ownerDocument.getSelection();

    if (
        domSelection?.anchorNode &&
        textBlock.contains(domSelection.anchorNode)
    ) {
        try {
            position = view.posAtDOM(
                domSelection.anchorNode,
                domSelection.anchorOffset,
            );
        } catch {
            position = null;
        }
    }

    const rect = textBlock.getBoundingClientRect();
    const clickIsInsideTarget =
        event.clientX >= rect.left &&
        event.clientX <= rect.right &&
        event.clientY >= rect.top &&
        event.clientY <= rect.bottom;

    if (position === null && clickIsInsideTarget) {
        position =
            view.posAtCoords({ left: event.clientX, top: event.clientY })
                ?.pos ?? null;
    }

    if (position === null) {
        try {
            position = view.posAtDOM(textBlock, textBlock.childNodes.length);
        } catch {
            return false;
        }
    }

    const resolvedPosition = view.state.doc.resolve(
        Math.max(0, Math.min(position, view.state.doc.content.size)),
    );
    const selection = TextSelection.near(resolvedPosition, -1);

    if (!selection.$from.parent.inlineContent) {
        return false;
    }

    view.dispatch(view.state.tr.setSelection(selection));
    view.focus();

    return false;
}

function openClipboardImage(event: ClipboardEvent): boolean {
    const file = Array.from(event.clipboardData?.files ?? []).find((item) =>
        item.type.startsWith('image/'),
    );

    if (!file) {
        return false;
    }

    event.preventDefault();
    void openImageDialog(file);

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

    void openImageDialog(file);

    return true;
}

async function uploadAsset(
    type: 'image' | 'document',
    file: File,
    metadata: {
        alt_text?: string | null;
        caption: string | null;
        decorative?: boolean;
    },
): Promise<UploadedLessonAsset | null> {
    const assetUploadUrl = await resolveAssetUploadUrl();

    if (!assetUploadUrl) {
        return null;
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

        request.open('POST', assetUploadUrl);
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
    const assetUploadUrl = await resolveAssetUploadUrl();

    if (!assetUploadUrl || !Number.isInteger(assetId) || assetId < 1) {
        dialogError.value = 'This lesson asset can no longer be edited.';

        return false;
    }

    uploading.value = true;
    uploadProgress.value = 100;
    dialogError.value = '';

    try {
        const response = await fetch(
            `${assetUploadUrl.replace(/\/$/, '')}/${assetId}`,
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

async function resolveAssetUploadUrl(): Promise<string | null> {
    if (props.assetUploadUrl) {
        return props.assetUploadUrl;
    }

    if (!props.ensureAssetUploadUrl || !props.canPrepareAssetUpload) {
        toolbarError.value =
            'Select a module before inserting a private image or PDF.';

        return null;
    }

    if (assetEndpointPreparation) {
        return assetEndpointPreparation;
    }

    preparingAssetEndpoint.value = true;
    toolbarError.value = '';
    assetEndpointPreparation = props.ensureAssetUploadUrl();

    try {
        const url = await assetEndpointPreparation;

        if (!url) {
            toolbarError.value = 'The private lesson draft is not ready yet.';
        }

        return url;
    } catch {
        toolbarError.value =
            'The private lesson draft could not be prepared. Try again.';

        return null;
    } finally {
        assetEndpointPreparation = null;
        preparingAssetEndpoint.value = false;
    }
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
            @mousedown="preserveToolbarSelection"
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
            {{
                canPrepareAssetUpload
                    ? 'Your private draft will be prepared when you insert an image, PDF, or open Preview.'
                    : 'Select a module to enable private image and PDF uploads.'
            }}
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
