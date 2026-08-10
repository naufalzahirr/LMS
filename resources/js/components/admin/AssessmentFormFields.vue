<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
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
    AssessmentPurpose,
    AuthoringOptions,
    SelectOption,
} from '@/types/assessment';

type EditableAssessment = {
    competency_id: number;
    title: string;
    code: string | null;
    description: string | null;
    purpose: AssessmentPurpose;
    instructions: string | null;
    shuffle_questions: boolean;
};

const props = defineProps<
    AuthoringOptions & {
        purposes: SelectOption<AssessmentPurpose>[];
        errors: Record<string, string>;
        initial?: EditableAssessment;
    }
>();

const initialCompetency = props.competencies.find(
    (item) => item.id === props.initial?.competency_id,
);
const initialCourse = props.courses.find(
    (item) => item.id === initialCompetency?.course_id,
);
const selection = reactive({
    program_id: initialCourse?.program_id.toString() ?? '',
    course_id: initialCourse?.id.toString() ?? '',
    competency_id: props.initial?.competency_id.toString() ?? '',
});
const availableCourses = computed(() =>
    props.courses.filter(
        (course) => course.program_id === Number(selection.program_id),
    ),
);
const availableCompetencies = computed(() =>
    props.competencies.filter(
        (item) => item.course_id === Number(selection.course_id),
    ),
);

watch(
    () => selection.program_id,
    () => {
        if (
            !availableCourses.value.some(
                (item) => item.id === Number(selection.course_id),
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
                (item) => item.id === Number(selection.competency_id),
            )
        ) {
            selection.competency_id = '';
        }
    },
);
</script>

<template>
    <div class="grid gap-4 md:grid-cols-3">
        <div class="grid gap-2">
            <Label for="program_id">Program</Label>
            <Select v-model="selection.program_id" required>
                <SelectTrigger id="program_id" class="w-full"
                    ><SelectValue placeholder="Select a program"
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem
                        v-for="program in programs"
                        :key="program.id"
                        :value="program.id.toString()"
                        >{{ program.name }}</SelectItem
                    ></SelectContent
                >
            </Select>
        </div>
        <div class="grid gap-2">
            <Label for="course_id">Course</Label>
            <Select
                v-model="selection.course_id"
                required
                :disabled="!selection.program_id"
            >
                <SelectTrigger id="course_id" class="w-full"
                    ><SelectValue placeholder="Select a course"
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem
                        v-for="course in availableCourses"
                        :key="course.id"
                        :value="course.id.toString()"
                        >{{ course.name }}</SelectItem
                    ></SelectContent
                >
            </Select>
        </div>
        <div class="grid gap-2">
            <Label for="competency_id">Competency</Label>
            <Select
                v-model="selection.competency_id"
                name="competency_id"
                required
                :disabled="!selection.course_id"
            >
                <SelectTrigger id="competency_id" class="w-full"
                    ><SelectValue placeholder="Select a competency"
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem
                        v-for="item in availableCompetencies"
                        :key="item.id"
                        :value="item.id.toString()"
                        >{{ item.code }} — {{ item.name }}</SelectItem
                    ></SelectContent
                >
            </Select>
            <InputError :message="errors.competency_id" />
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
            <Label for="code">Code</Label>
            <Input id="code" name="code" :default-value="initial?.code ?? ''" />
            <InputError :message="errors.code" />
        </div>
    </div>
    <div class="grid gap-2">
        <Label for="description">Description</Label>
        <Textarea
            id="description"
            name="description"
            :default-value="initial?.description ?? ''"
        />
        <InputError :message="errors.description" />
    </div>
    <div class="grid gap-2">
        <Label for="instructions">Instructions</Label>
        <Textarea
            id="instructions"
            name="instructions"
            :default-value="initial?.instructions ?? ''"
        />
        <InputError :message="errors.instructions" />
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="grid gap-2">
            <Label for="purpose">Purpose</Label>
            <Select
                name="purpose"
                :default-value="initial?.purpose ?? 'practice'"
                required
            >
                <SelectTrigger id="purpose" class="w-full"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem
                        v-for="purpose in purposes"
                        :key="purpose.value"
                        :value="purpose.value"
                        >{{ purpose.label }}</SelectItem
                    ></SelectContent
                >
            </Select>
            <InputError :message="errors.purpose" />
        </div>
        <label
            class="flex items-center gap-3 self-end rounded-lg border p-3 text-sm"
        >
            <input type="hidden" name="shuffle_questions" value="0" />
            <input
                type="checkbox"
                name="shuffle_questions"
                value="1"
                :checked="initial?.shuffle_questions ?? false"
                class="size-4"
            />
            Shuffle questions for learners
        </label>
    </div>
</template>
