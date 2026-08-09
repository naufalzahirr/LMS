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
import { index } from '@/routes/admin/modules';
import type {
    AcademicStatus,
    AcademicStatusOption,
    CompetencyOption,
    HierarchyCourseOption,
    ProgramOption,
} from '@/types/academic';

type EditableModule = {
    id: number;
    program_id: number;
    course_id: number;
    competency_id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    status: AcademicStatus;
};

const props = defineProps<{
    module: EditableModule;
    programs: ProgramOption[];
    courses: HierarchyCourseOption[];
    competencies: CompetencyOption[];
    statuses: AcademicStatusOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Modules', href: index() },
            { title: 'Edit module', href: index() },
        ],
    },
});

const selection = reactive({
    program_id: props.module.program_id.toString(),
    course_id: props.module.course_id.toString(),
    competency_id: props.module.competency_id.toString(),
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
    <Head :title="`Edit ${module.name}`" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Edit module"
            description="Update the module and its competency placement."
        />
        <Card class="max-w-2xl">
            <CardContent>
                <Form
                    v-bind="ModuleController.update.form(module.id)"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="program">Program</Label>
                            <Select v-model="selection.program_id" required>
                                <SelectTrigger id="program" class="w-full"
                                    ><SelectValue
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
                            <Select v-model="selection.course_id" required>
                                <SelectTrigger id="course" class="w-full"
                                    ><SelectValue
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
                        >
                            <SelectTrigger id="competency_id" class="w-full"
                                ><SelectValue
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
                        <Input
                            id="name"
                            name="name"
                            :default-value="module.name"
                            required
                            autofocus
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input
                            id="slug"
                            name="slug"
                            :default-value="module.slug"
                            required
                        />
                        <InputError :message="errors.slug" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            :default-value="module.description ?? ''"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="status">Status</Label>
                            <Select
                                name="status"
                                :default-value="module.status"
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
                                :default-value="module.sort_order"
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
                            >Save changes</Button
                        >
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
