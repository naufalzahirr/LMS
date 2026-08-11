<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import RichLessonEditor from '@/components/lesson/RichLessonEditor.vue';
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
    initial?: InitialLesson;
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
        <div>
            <h2 class="text-lg font-semibold">Lesson content</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Build one readable multimedia page. Place text, code, images,
                video, callouts, tables, and PDFs in the order learners need.
            </p>
        </div>
        <RichLessonEditor
            v-model="document"
            :asset-upload-url="assetUploadUrl"
        />
        <InputError :message="errors.content_document" />
    </section>
</template>
