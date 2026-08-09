<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type {
    AcademicStatus,
    AcademicStatusOption,
    CompetencyOption,
    HierarchyCourseOption,
    LessonType,
    LessonTypeOption,
    ModuleOption,
    ProgramOption,
} from '@/types/academic';

type InitialLesson = {
    program_id: number;
    course_id: number;
    competency_id: number;
    module_id: number;
    title: string;
    slug: string;
    lesson_type: LessonType;
    content: string | null;
    external_url: string | null;
    has_file: boolean;
    duration_minutes: number | null;
    sort_order: number;
    status: AcademicStatus;
};

const props = defineProps<{
    programs: ProgramOption[];
    courses: HierarchyCourseOption[];
    competencies: CompetencyOption[];
    modules: ModuleOption[];
    lessonTypes: LessonTypeOption[];
    statuses: AcademicStatusOption[];
    errors: Record<string, string>;
    initial?: InitialLesson;
}>();

const selection = reactive({
    program_id: props.initial?.program_id.toString() ?? '',
    course_id: props.initial?.course_id.toString() ?? '',
    competency_id: props.initial?.competency_id.toString() ?? '',
    module_id: props.initial?.module_id.toString() ?? '',
});
const lessonType = ref<LessonType>(props.initial?.lesson_type ?? 'text');
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
const usesExternalUrl = computed(() =>
    ['video', 'link'].includes(lessonType.value),
);
const usesFile = computed(() =>
    ['document', 'image'].includes(lessonType.value),
);
const fileIsRequired = computed(
    () =>
        !props.initial?.has_file ||
        props.initial.lesson_type !== lessonType.value,
);
const acceptedFileTypes = computed(() =>
    lessonType.value === 'document'
        ? 'application/pdf,.pdf'
        : 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp',
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
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="grid gap-2">
            <Label for="program">Program</Label>
            <Select v-model="selection.program_id" required>
                <SelectTrigger id="program" class="w-full"
                    ><SelectValue placeholder="Select a program"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="program in programs"
                        :key="program.id"
                        :value="program.id.toString()"
                        >{{ program.name }}</SelectItem
                    >
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
                <SelectTrigger id="course" class="w-full"
                    ><SelectValue placeholder="Select a course"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="course in availableCourses"
                        :key="course.id"
                        :value="course.id.toString()"
                        >{{ course.name }}</SelectItem
                    >
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
                <SelectTrigger id="competency" class="w-full"
                    ><SelectValue placeholder="Select a competency"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="competency in availableCompetencies"
                        :key="competency.id"
                        :value="competency.id.toString()"
                        >{{ competency.code }} —
                        {{ competency.name }}</SelectItem
                    >
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
                <SelectTrigger id="module_id" class="w-full"
                    ><SelectValue placeholder="Select a module"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="module in availableModules"
                        :key="module.id"
                        :value="module.id.toString()"
                        >{{ module.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <InputError :message="errors.module_id" />
        </div>
    </div>

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
    <div class="grid gap-2">
        <Label for="lesson_type">Lesson type</Label>
        <Select v-model="lessonType" name="lesson_type" required>
            <SelectTrigger id="lesson_type" class="w-full"
                ><SelectValue
            /></SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="type in lessonTypes"
                    :key="type.value"
                    :value="type.value"
                    >{{ type.label }}</SelectItem
                >
            </SelectContent>
        </Select>
        <InputError :message="errors.lesson_type" />
    </div>

    <div v-if="usesExternalUrl" class="grid gap-2">
        <Label for="external_url">External URL</Label>
        <Input
            id="external_url"
            name="external_url"
            type="url"
            :default-value="initial?.external_url ?? ''"
            placeholder="https://..."
            required
        />
        <InputError :message="errors.external_url" />
    </div>

    <div v-if="usesFile" class="grid gap-2">
        <Label for="file">{{
            lessonType === 'document' ? 'PDF document' : 'Image file'
        }}</Label>
        <input
            id="file"
            name="file"
            type="file"
            :accept="acceptedFileTypes"
            :required="fileIsRequired"
            class="h-10 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-2 file:py-1 file:text-foreground disabled:opacity-50"
        />
        <p v-if="initial?.has_file" class="text-xs text-muted-foreground">
            Leave blank to keep the current managed file.
        </p>
        <p v-else class="text-xs text-muted-foreground">
            {{
                lessonType === 'document'
                    ? 'PDF only, up to 20 MB.'
                    : 'JPG, PNG, or WebP, up to 10 MB.'
            }}
        </p>
        <InputError :message="errors.file" />
    </div>

    <div class="grid gap-2">
        <Label for="content">{{
            lessonType === 'text' ? 'Content' : 'Notes / description'
        }}</Label>
        <Textarea
            id="content"
            name="content"
            :default-value="initial?.content ?? ''"
            :required="lessonType === 'text'"
            class="min-h-40"
        />
        <InputError :message="errors.content" />
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="grid gap-2">
            <Label for="duration_minutes">Duration (minutes)</Label>
            <Input
                id="duration_minutes"
                name="duration_minutes"
                type="number"
                min="0"
                :default-value="initial?.duration_minutes ?? ''"
                placeholder="Optional"
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
                <SelectTrigger id="status" class="w-full"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="status in statuses"
                        :key="status.value"
                        :value="status.value"
                        >{{ status.label }}</SelectItem
                    >
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
</template>
