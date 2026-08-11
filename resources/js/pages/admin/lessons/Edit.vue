<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import LessonController from '@/actions/App/Http/Controllers/Admin/LessonController';
import LessonFormFields from '@/components/admin/LessonFormFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index, show } from '@/routes/admin/lessons';
import type {
    AcademicStatus,
    AcademicStatusOption,
    CompetencyOption,
    HierarchyCourseOption,
    ModuleOption,
    ProgramOption,
} from '@/types/academic';
import type { LessonDocument } from '@/types/lesson-content';

type EditableLesson = {
    id: number;
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

defineProps<{
    lesson: EditableLesson;
    programs: ProgramOption[];
    courses: HierarchyCourseOption[];
    competencies: CompetencyOption[];
    modules: ModuleOption[];
    statuses: AcademicStatusOption[];
    assetUploadUrl: string;
    previewUrl: string;
    draftEnsureUrl: null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lessons', href: index() },
            { title: 'Edit lesson', href: index() },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${lesson.title}`" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Edit lesson"
            description="Update lesson information and its multimedia learning flow."
        />
        <Card class="max-w-6xl">
            <CardContent>
                <Form
                    :action="LessonController.update.url(lesson.id)"
                    method="post"
                    class="space-y-8"
                    v-slot="{ errors, processing }"
                >
                    <input type="hidden" name="_method" value="put" />
                    <LessonFormFields
                        :programs="programs"
                        :courses="courses"
                        :competencies="competencies"
                        :modules="modules"
                        :statuses="statuses"
                        :errors="errors"
                        :initial="lesson"
                        :content-document="lesson.content_document"
                        :asset-upload-url="assetUploadUrl"
                        :preview-url="previewUrl"
                        :draft-ensure-url="draftEnsureUrl"
                    />
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child
                            ><Link :href="show(lesson.id)">Cancel</Link></Button
                        >
                        <Button type="submit" :disabled="processing"
                            >Save changes</Button
                        >
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
