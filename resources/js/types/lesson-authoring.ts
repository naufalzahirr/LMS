export type LessonAuthoringProps = {
    assetUploadUrl: string | null;
    checkpointUrl: string | null;
    previewUrl: string | null;
    draftEnsureUrl: string | null;
};

export type NewLessonAuthoringProps = LessonAuthoringProps & {
    assetUploadUrl: null;
    checkpointUrl: null;
    previewUrl: null;
    draftEnsureUrl: string;
};

export type ExistingLessonAuthoringProps = LessonAuthoringProps & {
    assetUploadUrl: string;
    checkpointUrl: string;
    previewUrl: string;
    draftEnsureUrl: null;
};

export type LessonDraftAuthoringState = {
    id: number;
    moduleId: number;
    assetUploadUrl: string;
    checkpointUrl: string;
    previewUrl: string;
    discardUrl: string;
    expiresAt: string | null;
};

export type LessonDraftEnsureResponse = {
    draft?: {
        id: number;
        expires_at: string | null;
        asset_upload_url: string;
        checkpoint_url: string;
        preview_url: string;
        discard_url: string;
    };
    message?: string;
    errors?: Record<string, string[]>;
};

export type EnsureLessonAssetUpload = () => Promise<string | null>;

export type EnsureLessonCheckpointAuthoring = () => Promise<string | null>;
