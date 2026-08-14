export type LessonCodeLanguage =
    | 'plain'
    | 'html'
    | 'css'
    | 'javascript'
    | 'typescript'
    | 'php'
    | 'sql'
    | 'python'
    | 'cpp'
    | 'java'
    | 'json'
    | 'bash';

export type LessonCalloutType = 'info' | 'tip' | 'warning' | 'important';

export type LessonCheckpointType =
    'multiple_choice' | 'multiple_select' | 'true_false' | 'fill_blank';

export type LessonCheckpointOption = {
    id: string;
    text: string;
};

export type LessonCheckpointPublicPayload = {
    id: number;
    type: LessonCheckpointType;
    type_label: string;
    prompt: string;
    options: LessonCheckpointOption[];
};

export type LessonCheckpointAuthorPayload = LessonCheckpointPublicPayload & {
    correct_feedback: string | null;
    incorrect_feedback: string | null;
    explanation: string | null;
    correct_option_ids: string[];
    correct_boolean: boolean | null;
    accepted_answers: string[];
    update_url: string;
};

export type LessonCheckpointAuthorInput = {
    checkpoint_type: LessonCheckpointType;
    prompt: string;
    correct_feedback: string | null;
    incorrect_feedback: string | null;
    explanation: string | null;
    options?: LessonCheckpointOption[];
    correct_option_ids?: string[];
    correct_boolean?: boolean;
    accepted_answers?: string[];
};

export type LessonCheckpointStudentState = LessonCheckpointPublicPayload & {
    explanation: string | null;
    interactive: true;
    can_submit: boolean;
    mastered: boolean;
    attempt_count: number;
    submit_url: string;
};

export type LessonCheckpointPreviewPayload = LessonCheckpointPublicPayload & {
    explanation: string | null;
    interactive: false;
};

export type LessonCheckpointPayload =
    | LessonCheckpointAuthorPayload
    | LessonCheckpointStudentState
    | LessonCheckpointPreviewPayload;

export type LessonCheckpointResult = {
    correct: boolean;
    mastered: boolean;
    feedback: string;
    explanation: string | null;
    attempt_count: number;
};

export type LessonMark = {
    type: 'bold' | 'italic' | 'link';
    attrs?: {
        href?: string;
        target?: string | null;
        rel?: string | null;
        class?: string | null;
    };
};

export type LessonNode = {
    type: string;
    text?: string;
    marks?: LessonMark[];
    attrs?: Record<string, unknown>;
    content?: LessonNode[];
};

export type LessonDocument = {
    type: 'doc';
    content: LessonNode[];
};

export type UploadedLessonAsset = {
    id: number;
    asset_type: 'image' | 'document';
    original_name: string;
    mime_type: string;
    file_size: number;
    alt_text: string | null;
    caption: string | null;
    url: string;
    download_url: string;
};

const presentationAttributes = new Set([
    'downloadUrl',
    'embedUrl',
    'originalName',
    'mimeType',
    'fileSize',
    'checkpoint',
]);

export function canonicalLessonDocument(
    document: LessonDocument,
): LessonDocument {
    return stripNode(document) as LessonDocument;
}

function stripNode(node: LessonNode | LessonDocument): LessonNode {
    const clean: LessonNode = { type: node.type };

    if ('text' in node && typeof node.text === 'string') {
        clean.text = node.text;
    }

    if ('marks' in node && node.marks) {
        clean.marks = node.marks;
    }

    if ('attrs' in node && node.attrs) {
        clean.attrs = Object.fromEntries(
            Object.entries(node.attrs).filter(
                ([key, value]) =>
                    !presentationAttributes.has(key) &&
                    (key !== 'url' || node.type === 'externalVideo') &&
                    !(
                        key === 'align' &&
                        value === null &&
                        ['tableCell', 'tableHeader'].includes(node.type)
                    ),
            ),
        );
    }

    if (node.content) {
        clean.content = node.content.map(stripNode);
    }

    return clean;
}
