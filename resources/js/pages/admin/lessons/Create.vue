<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
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
    LessonTypeOption,
    ModuleOption,
    ProgramOption,
} from '@/types/academic';

defineProps<{
    programs: ProgramOption[];
    courses: HierarchyCourseOption[];
    competencies: CompetencyOption[];
    modules: ModuleOption[];
    lessonTypes: LessonTypeOption[];
    statuses: AcademicStatusOption[];
}>();

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
            description="Add reusable learning content to a module."
        />
        <Card class="max-w-3xl">
            <CardContent>
                <Form
                    v-bind="LessonController.store.form()"
                    enctype="multipart/form-data"
                    reset-on-success
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <LessonFormFields
                        :programs="programs"
                        :courses="courses"
                        :competencies="competencies"
                        :modules="modules"
                        :lesson-types="lessonTypes"
                        :statuses="statuses"
                        :errors="errors"
                    />
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child
                            ><Link :href="index()">Cancel</Link></Button
                        >
                        <Button type="submit" :disabled="processing"
                            >Create lesson</Button
                        >
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
