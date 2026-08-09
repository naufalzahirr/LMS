<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import CompetencyController from '@/actions/App/Http/Controllers/Admin/CompetencyController';
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
import { index } from '@/routes/admin/competencies';
import type {
    AcademicStatus,
    AcademicStatusOption,
    CourseOption,
} from '@/types/academic';

type EditableCompetency = {
    id: number;
    course_id: number;
    code: string;
    name: string;
    slug: string;
    description: string | null;
    learning_objectives: string | null;
    sort_order: number;
    status: AcademicStatus;
};

defineProps<{
    competency: EditableCompetency;
    courses: CourseOption[];
    statuses: AcademicStatusOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Competencies', href: index() },
            { title: 'Edit competency', href: index() },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${competency.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Edit competency"
            description="Update competency details and learning objectives."
        />

        <Card class="max-w-2xl">
            <CardContent>
                <Form
                    v-bind="CompetencyController.update.form(competency.id)"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="course_id">Course</Label>
                        <Select
                            name="course_id"
                            :default-value="competency.course_id.toString()"
                            required
                        >
                            <SelectTrigger id="course_id" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="course in courses"
                                    :key="course.id"
                                    :value="course.id.toString()"
                                >
                                    {{ course.program }} — {{ course.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.course_id" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="code">Code</Label>
                            <Input
                                id="code"
                                name="code"
                                :default-value="competency.code"
                                required
                                autofocus
                            />
                            <InputError :message="errors.code" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                :default-value="competency.name"
                                required
                            />
                            <InputError :message="errors.name" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input
                            id="slug"
                            name="slug"
                            :default-value="competency.slug"
                            required
                        />
                        <InputError :message="errors.slug" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            :default-value="competency.description ?? ''"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="learning_objectives"
                            >Learning objectives</Label
                        >
                        <Textarea
                            id="learning_objectives"
                            name="learning_objectives"
                            :default-value="
                                competency.learning_objectives ?? ''
                            "
                        />
                        <InputError :message="errors.learning_objectives" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="status">Status</Label>
                            <Select
                                name="status"
                                :default-value="competency.status"
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
                                :default-value="competency.sort_order"
                                required
                            />
                            <InputError :message="errors.sort_order" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child>
                            <Link :href="index()">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="processing">
                            Save changes
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
