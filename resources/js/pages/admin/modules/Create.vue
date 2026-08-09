<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import ModuleController from '@/actions/App/Http/Controllers/Admin/ModuleController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { create, index } from '@/routes/admin/modules';
import type {
    AcademicStatusOption,
    CompetencyOption,
    HierarchyCourseOption,
    ProgramOption,
} from '@/types/academic';

const props = defineProps<{
    programs: ProgramOption[];
    courses: HierarchyCourseOption[];
    competencies: CompetencyOption[];
    statuses: AcademicStatusOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Modules', href: index() },
            { title: 'Create module', href: create() },
        ],
    },
});

const selection = reactive({
    program_id: '',
    course_id: '',
    competency_id: '',
});
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
</script>

<template>
    <Head title="Create module" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Create module"
            description="Add a learning-content module to a competency."
        />
        <Card class="max-w-2xl">
            <CardContent>
                <Form
                    v-bind="ModuleController.store.form()"
                    reset-on-success
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="program">Program</Label>
                            <Select v-model="selection.program_id" required>
                                <SelectTrigger id="program" class="w-full"
                                    ><SelectValue
                                        placeholder="Select a program"
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
                        <InputError :message="errors.competency_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" name="name" required autofocus />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input
                            id="slug"
                            name="slug"
                            placeholder="Generated from name when blank"
                        />
                        <InputError :message="errors.slug" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <Textarea id="description" name="description" />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="status">Status</Label>
                            <Select
                                name="status"
                                default-value="active"
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
                                :default-value="0"
                                required
                            />
                            <InputError :message="errors.sort_order" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child
                            ><Link :href="index()">Cancel</Link></Button
                        >
                        <Button type="submit" :disabled="processing"
                            >Create module</Button
                        >
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
