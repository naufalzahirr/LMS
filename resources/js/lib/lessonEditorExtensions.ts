import { mergeAttributes, Node } from '@tiptap/core';

export const LessonImage = Node.create({
    name: 'lessonImage',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            lessonAssetId: { default: null },
            altText: { default: '' },
            caption: { default: null },
            alignment: { default: 'center' },
            size: { default: 'large' },
            decorative: { default: false },
            url: { default: null },
            originalName: { default: null },
            mimeType: { default: null },
            fileSize: { default: null },
        };
    },

    parseHTML() {
        return [{ tag: 'figure[data-lesson-image]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        const caption = node.attrs.caption as string | null;

        return [
            'figure',
            mergeAttributes(HTMLAttributes, {
                'data-lesson-image': '',
                'data-alignment': node.attrs.alignment,
                'data-size': node.attrs.size,
            }),
            [
                'img',
                {
                    src: node.attrs.url ?? '',
                    alt: node.attrs.decorative ? '' : node.attrs.altText,
                },
            ],
            ...(caption ? [['figcaption', {}, caption]] : []),
        ];
    },
});

export const ExternalVideo = Node.create({
    name: 'externalVideo',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            url: { default: null },
            title: { default: 'Lesson video' },
            caption: { default: null },
            provider: { default: null },
            videoId: { default: null },
            embedUrl: { default: null },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-external-video]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return [
            'div',
            mergeAttributes(HTMLAttributes, {
                'data-external-video': '',
                class: 'lesson-editor-embed',
            }),
            ['strong', {}, node.attrs.title],
            ['span', {}, node.attrs.url],
        ];
    },
});

export const LessonCallout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'inline*',
    defining: true,
    draggable: true,

    addAttributes() {
        return { type: { default: 'info' } };
    },

    parseHTML() {
        return [{ tag: 'aside[data-lesson-callout]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return [
            'aside',
            mergeAttributes(HTMLAttributes, {
                'data-lesson-callout': node.attrs.type,
            }),
            ['span', { class: 'lesson-editor-callout-label' }, node.attrs.type],
            ['div', {}, 0],
        ];
    },
});

export const LessonFile = Node.create({
    name: 'lessonFile',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            lessonAssetId: { default: null },
            title: { default: 'PDF resource' },
            caption: { default: null },
            url: { default: null },
            downloadUrl: { default: null },
            originalName: { default: null },
            mimeType: { default: null },
            fileSize: { default: null },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-lesson-file]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return [
            'div',
            mergeAttributes(HTMLAttributes, {
                'data-lesson-file': '',
                class: 'lesson-editor-file',
            }),
            ['strong', {}, node.attrs.title],
            ...(node.attrs.caption
                ? [['span', {}, node.attrs.caption as string]]
                : []),
        ];
    },
});

export const LessonCheckpoint = Node.create({
    name: 'lessonCheckpoint',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            checkpointId: { default: null },
            checkpoint: { default: null },
        };
    },

    parseHTML() {
        return [{ tag: 'section[data-lesson-checkpoint]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        const checkpoint = node.attrs.checkpoint as {
            type_label?: string;
            prompt?: string;
        } | null;

        return [
            'section',
            mergeAttributes(HTMLAttributes, {
                'data-lesson-checkpoint': '',
                class: 'lesson-editor-checkpoint',
            }),
            [
                'span',
                { class: 'lesson-editor-checkpoint-label' },
                checkpoint?.type_label ?? 'Checkpoint',
            ],
            [
                'strong',
                {},
                checkpoint?.prompt ?? 'Interactive learning checkpoint',
            ],
            ['span', {}, 'Select this block to edit or remove it.'],
        ];
    },
});
