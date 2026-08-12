<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import LessonController from '@/actions/App/Http/Controllers/Admin/LessonController';
import LessonFormFields from '@/components/admin/LessonFormFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, index } from '@/routes/admin/lessons';
import type {
    AcademicStatusOption,
    CompetencyOption,
    HierarchyCourseOption,
    ModuleOption,
    ProgramOption,
} from '@/types/academic';
import type { NewLessonAuthoringProps } from '@/types/lesson-authoring';
import type { LessonDocument } from '@/types/lesson-content';

defineProps<
    {
        programs: ProgramOption[];
        courses: HierarchyCourseOption[];
        competencies: CompetencyOption[];
        modules: ModuleOption[];
        statuses: AcademicStatusOption[];
        contentDocument: LessonDocument;
    } & NewLessonAuthoringProps
>();

const draftDiscardUrl = ref<string | null>(null);
const cancelling = ref(false);
const authoringBusy = ref(false);

function rememberDraft(draft: { id: number; discardUrl: string }): void {
    draftDiscardUrl.value = draft.discardUrl;
}

async function cancelAuthoring(): Promise<void> {
    cancelling.value = true;

    if (draftDiscardUrl.value) {
        try {
            await fetch(draftDiscardUrl.value, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        globalThis.document
                            .querySelector<HTMLMetaElement>(
                                'meta[name="csrf-token"]',
                            )
                            ?.getAttribute('content') ?? '',
                },
                credentials: 'same-origin',
            });
        } catch {
            // The scheduled cleanup remains the fallback for an unreachable server.
        }
    }

    router.visit(index().url);
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lessons', href: index() },
            { title: 'Create lesson', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Create lesson" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Create lesson"
            description="Build a rich multimedia learning page inside a module."
        />
        <Card class="max-w-6xl">
            <CardContent>
                <Form
                    v-bind="LessonController.store.form()"
                    reset-on-success
                    class="space-y-8"
                    v-slot="{ errors, processing }"
                >
                    <LessonFormFields
                        :programs="programs"
                        :courses="courses"
                        :competencies="competencies"
                        :modules="modules"
                        :statuses="statuses"
                        :errors="errors"
                        :content-document="contentDocument"
                        :asset-upload-url="assetUploadUrl"
                        :checkpoint-url="checkpointUrl"
                        :preview-url="previewUrl"
                        :draft-ensure-url="draftEnsureUrl"
                        @authoring-busy="authoringBusy = $event"
                        @draft-ready="rememberDraft"
                    />
                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="
                                cancelling || processing || authoringBusy
                            "
                            @click="cancelAuthoring"
                        >
                            {{ cancelling ? 'Closing…' : 'Cancel' }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="processing || authoringBusy"
                            >Create lesson</Button
                        >
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
