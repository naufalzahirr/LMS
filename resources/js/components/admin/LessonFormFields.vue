<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import LessonContentRenderer from '@/components/lesson/LessonContentRenderer.vue';
import RichLessonEditor from '@/components/lesson/RichLessonEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    AcademicStatus,
    AcademicStatusOption,
    CompetencyOption,
    HierarchyCourseOption,
    ModuleOption,
    ProgramOption,
} from '@/types/academic';
import { canonicalLessonDocument } from '@/types/lesson-content';
import type { LessonDocument } from '@/types/lesson-content';

type InitialLesson = {
    program_id: number;
    course_id: number;
    competency_id: number;
    module_id: number;
    title: string;
    slug: string;
    content_document: LessonDocument;
    duration_minutes: number | null;
    sort_order: number;
    status: AcademicStatus;
};

const props = defineProps<{
    programs: ProgramOption[];
    courses: HierarchyCourseOption[];
    competencies: CompetencyOption[];
    modules: ModuleOption[];
    statuses: AcademicStatusOption[];
    errors: Record<string, string>;
    contentDocument: LessonDocument;
    assetUploadUrl: string | null;
    previewUrl: string | null;
    draftEnsureUrl: string | null;
    initial?: InitialLesson;
}>();

const emit = defineEmits<{
    'draft-ready': [draft: { id: number; discardUrl: string }];
}>();

const selection = reactive({
    program_id: props.initial?.program_id.toString() ?? '',
    course_id: props.initial?.course_id.toString() ?? '',
    competency_id: props.initial?.competency_id.toString() ?? '',
    module_id: props.initial?.module_id.toString() ?? '',
});
const document = ref<LessonDocument>(
    props.initial?.content_document ?? props.contentDocument,
);
const activeAssetUploadUrl = ref(props.assetUploadUrl);
const activePreviewUrl = ref(props.previewUrl);
const draftAssetUploadUrl = ref<string | null>(null);
const draftPreviewUrl = ref<string | null>(null);
const draftId = ref<number | null>(null);
const draftModuleId = ref<number | null>(null);
const requestedDraftModuleId = ref<number | null>(null);
const draftLoading = ref(false);
const draftError = ref('');
const mode = ref<'edit' | 'preview'>('edit');
const previewDocument = ref<LessonDocument | null>(null);
const previewLoading = ref(false);
const previewError = ref('');
const availableCourses = computed(() =>
    props.courses.filter(
        (course) => course.program_id === Number(selection.program_id),
    ),
);
const availableCompetencies = computed(() =>
    props.competencies.filter(
        (competency) => competency.course_id === Number(selection.course_id),
    ),
);
const availableModules = computed(() =>
    props.modules.filter(
        (module) => module.competency_id === Number(selection.competency_id),
    ),
);

watch(
    () => selection.program_id,
    () => {
        if (
            !availableCourses.value.some(
                (course) => course.id === Number(selection.course_id),
            )
        ) {
            selection.course_id = '';
        }
    },
);
watch(
    () => selection.course_id,
    () => {
        if (
            !availableCompetencies.value.some(
                (competency) =>
                    competency.id === Number(selection.competency_id),
            )
        ) {
            selection.competency_id = '';
        }
    },
);
watch(
    () => selection.competency_id,
    () => {
        if (
            !availableModules.value.some(
                (module) => module.id === Number(selection.module_id),
            )
        ) {
            selection.module_id = '';
        }
    },
);

watch(
    () => selection.module_id,
    (moduleId) => {
        if (!props.draftEnsureUrl) {
            return;
        }

        const parsed = Number(moduleId);
        requestedDraftModuleId.value =
            Number.isInteger(parsed) && parsed > 0 ? parsed : null;

        if (requestedDraftModuleId.value === draftModuleId.value) {
            activeAssetUploadUrl.value = draftAssetUploadUrl.value;
            activePreviewUrl.value = draftPreviewUrl.value;

            return;
        }

        activeAssetUploadUrl.value = null;
        activePreviewUrl.value = null;
        mode.value = 'edit';
        void prepareRequestedDraft();
    },
);

async function prepareRequestedDraft(): Promise<void> {
    if (draftLoading.value || !props.draftEnsureUrl) {
        return;
    }

    draftLoading.value = true;
    draftError.value = '';

    try {
        while (
            requestedDraftModuleId.value !== null &&
            requestedDraftModuleId.value !== draftModuleId.value
        ) {
            const moduleId = requestedDraftModuleId.value;
            const response = await fetch(props.draftEnsureUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    module_id: moduleId,
                    draft_id: draftId.value,
                }),
                credentials: 'same-origin',
            });
            const payload = (await response.json()) as {
                draft?: {
                    id: number;
                    asset_upload_url: string;
                    preview_url: string;
                    discard_url: string;
                };
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (!response.ok || !payload.draft) {
                draftError.value = responseError(
                    payload,
                    'The private lesson draft could not be prepared.',
                );

                return;
            }

            draftId.value = payload.draft.id;
            draftModuleId.value = moduleId;
            draftAssetUploadUrl.value = payload.draft.asset_upload_url;
            draftPreviewUrl.value = payload.draft.preview_url;

            if (requestedDraftModuleId.value === moduleId) {
                activeAssetUploadUrl.value = draftAssetUploadUrl.value;
                activePreviewUrl.value = draftPreviewUrl.value;
            }

            emit('draft-ready', {
                id: payload.draft.id,
                discardUrl: payload.draft.discard_url,
            });
        }
    } catch {
        draftError.value =
            'The draft could not be prepared. Check your connection and try again.';
    } finally {
        draftLoading.value = false;
    }
}

async function openPreview(): Promise<void> {
    previewError.value = '';

    if (!activePreviewUrl.value) {
        previewError.value =
            'Select a module and wait for the private draft before previewing.';

        return;
    }

    previewLoading.value = true;

    try {
        const response = await fetch(activePreviewUrl.value, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                content_document: canonicalLessonDocument(document.value),
            }),
            credentials: 'same-origin',
        });
        const payload = (await response.json()) as {
            content_document?: LessonDocument;
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (!response.ok || !payload.content_document) {
            previewError.value = responseError(
                payload,
                'The lesson preview could not be generated.',
            );

            return;
        }

        previewDocument.value = payload.content_document;
        mode.value = 'preview';
    } catch {
        previewError.value =
            'The preview failed. Check your connection and try again.';
    } finally {
        previewLoading.value = false;
    }
}

function responseError(
    payload: { message?: string; errors?: Record<string, string[]> },
    fallback: string,
): string {
    return (
        Object.values(payload.errors ?? {})[0]?.[0] ??
        payload.message ??
        fallback
    );
}

function csrfToken(): string {
    return (
        globalThis.document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}
</script>

<template>
    <section class="space-y-5">
        <div>
            <h2 class="text-lg font-semibold">Lesson information</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Place this lesson in the learning hierarchy and define its
                publishing details.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="program">Program</Label>
                <Select v-model="selection.program_id" required>
                    <SelectTrigger id="program" class="w-full">
                        <SelectValue placeholder="Select a program" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="program in programs"
                            :key="program.id"
                            :value="program.id.toString()"
                        >
                            {{ program.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-2">
                <Label for="course">Course</Label>
                <Select
                    v-model="selection.course_id"
                    required
                    :disabled="!selection.program_id"
                >
                    <SelectTrigger id="course" class="w-full">
                        <SelectValue placeholder="Select a course" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="course in availableCourses"
                            :key="course.id"
                            :value="course.id.toString()"
                        >
                            {{ course.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="competency">Competency</Label>
                <Select
                    v-model="selection.competency_id"
                    required
                    :disabled="!selection.course_id"
                >
                    <SelectTrigger id="competency" class="w-full">
                        <SelectValue placeholder="Select a competency" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="competency in availableCompetencies"
                            :key="competency.id"
                            :value="competency.id.toString()"
                        >
                            {{ competency.code }} — {{ competency.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-2">
                <Label for="module_id">Module</Label>
                <Select
                    v-model="selection.module_id"
                    name="module_id"
                    required
                    :disabled="!selection.competency_id"
                >
                    <SelectTrigger id="module_id" class="w-full">
                        <SelectValue placeholder="Select a module" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="module in availableModules"
                            :key="module.id"
                            :value="module.id.toString()"
                        >
                            {{ module.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.module_id" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="title">Title</Label>
                <Input
                    id="title"
                    name="title"
                    :default-value="initial?.title"
                    required
                    autofocus
                />
                <InputError :message="errors.title" />
            </div>
            <div class="grid gap-2">
                <Label for="slug">Slug</Label>
                <Input
                    id="slug"
                    name="slug"
                    :default-value="initial?.slug"
                    :placeholder="
                        initial ? undefined : 'Generated from title when blank'
                    "
                    :required="Boolean(initial)"
                />
                <InputError :message="errors.slug" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label for="duration_minutes">Estimated duration</Label>
                <Input
                    id="duration_minutes"
                    name="duration_minutes"
                    type="number"
                    min="0"
                    :default-value="initial?.duration_minutes ?? ''"
                    placeholder="Minutes"
                />
                <InputError :message="errors.duration_minutes" />
            </div>
            <div class="grid gap-2">
                <Label for="status">Status</Label>
                <Select
                    name="status"
                    :default-value="initial?.status ?? 'active'"
                    required
                >
                    <SelectTrigger id="status" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.status" />
            </div>
            <div class="grid gap-2">
                <Label for="sort_order">Sort order</Label>
                <Input
                    id="sort_order"
                    name="sort_order"
                    type="number"
                    min="0"
                    :default-value="initial?.sort_order ?? 0"
                    required
                />
                <InputError :message="errors.sort_order" />
            </div>
        </div>
    </section>

    <div class="border-t" />

    <section class="space-y-4">
        <input v-if="draftId" type="hidden" name="draft_id" :value="draftId" />
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <h2 class="text-lg font-semibold">Lesson content</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Build one readable multimedia page. Place text, code,
                    images, video, callouts, tables, and PDFs in the order
                    learners need.
                </p>
            </div>
            <div
                class="inline-flex self-start rounded-lg border bg-muted/30 p-1"
            >
                <Button
                    type="button"
                    size="sm"
                    :variant="mode === 'edit' ? 'default' : 'ghost'"
                    @click="mode = 'edit'"
                >
                    Edit
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="mode === 'preview' ? 'default' : 'ghost'"
                    :disabled="previewLoading || draftLoading"
                    @click="openPreview"
                >
                    {{ previewLoading ? 'Preparing…' : 'Preview' }}
                </Button>
            </div>
        </div>
        <p
            v-if="draftLoading"
            class="rounded-lg border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
            aria-live="polite"
        >
            Preparing a private lesson draft for uploads and preview…
        </p>
        <div
            v-if="draftError || previewError"
            class="flex items-center gap-3"
            role="alert"
        >
            <p class="text-sm text-destructive">
                {{ draftError || previewError }}
            </p>
            <Button
                v-if="draftError"
                type="button"
                size="sm"
                variant="outline"
                @click="prepareRequestedDraft"
            >
                Retry
            </Button>
        </div>
        <RichLessonEditor
            v-show="mode === 'edit'"
            v-model="document"
            :asset-upload-url="activeAssetUploadUrl"
        />
        <div
            v-if="mode === 'preview' && previewDocument"
            class="rounded-lg border bg-background px-5 py-8 md:px-10"
        >
            <div class="mx-auto max-w-4xl">
                <p
                    class="mb-6 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Author preview · no learner activity is recorded
                </p>
                <LessonContentRenderer :document="previewDocument" />
            </div>
        </div>
        <InputError :message="errors.content_document" />
    </section>
</template>
