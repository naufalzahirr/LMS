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
    DeliveryCourseOption,
    DeliveryProgramOption,
    LearningClassStatus,
    SelectOption,
} from '@/types/delivery';

type EditableLearningClass = {
    course_id: number;
    name: string;
    code: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    status: LearningClassStatus;
};

const props = defineProps<{
    programs: DeliveryProgramOption[];
    courses: DeliveryCourseOption[];
    statuses: SelectOption<LearningClassStatus>[];
    errors: Record<string, string>;
    initial?: EditableLearningClass;
}>();

const initialCourse = props.courses.find(
    (course) => course.id === props.initial?.course_id,
);
const selection = reactive({
    program_id: initialCourse?.program_id.toString() ?? '',
    course_id: props.initial?.course_id.toString() ?? '',
});
const availableCourses = computed(() =>
    props.courses.filter(
        (course) => course.program_id === Number(selection.program_id),
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
            <Label for="course_id">Course</Label>
            <Select
                v-model="selection.course_id"
                name="course_id"
                required
                :disabled="!selection.program_id"
            >
                <SelectTrigger id="course_id" class="w-full"
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
            <InputError :message="errors.course_id" />
        </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="grid gap-2">
            <Label for="name">Class name</Label>
            <Input
                id="name"
                name="name"
                :default-value="initial?.name"
                required
                autofocus
            />
            <InputError :message="errors.name" />
        </div>
        <div class="grid gap-2">
            <Label for="code">Code</Label>
            <Input
                id="code"
                name="code"
                :default-value="initial?.code"
                placeholder="FE-BATCH-A"
                required
            />
            <InputError :message="errors.code" />
        </div>
    </div>
    <div class="grid gap-2">
        <Label for="description">Description</Label>
        <Textarea
            id="description"
            name="description"
            :default-value="initial?.description ?? undefined"
        />
        <InputError :message="errors.description" />
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="grid gap-2">
            <Label for="start_date">Start date</Label>
            <Input
                id="start_date"
                name="start_date"
                type="date"
                :default-value="initial?.start_date ?? undefined"
            />
            <InputError :message="errors.start_date" />
        </div>
        <div class="grid gap-2">
            <Label for="end_date">End date</Label>
            <Input
                id="end_date"
                name="end_date"
                type="date"
                :default-value="initial?.end_date ?? undefined"
            />
            <InputError :message="errors.end_date" />
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
    </div>
</template>
