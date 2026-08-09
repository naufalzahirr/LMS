<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import CourseController from '@/actions/App/Http/Controllers/Admin/CourseController';
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
import { index } from '@/routes/admin/courses';
import type {
    AcademicStatus,
    AcademicStatusOption,
    ProgramOption,
} from '@/types/academic';

type EditableCourse = {
    id: number;
    program_id: number;
    name: string;
    slug: string;
    code: string | null;
    description: string | null;
    status: AcademicStatus;
    sort_order: number;
};

defineProps<{
    course: EditableCourse;
    programs: ProgramOption[];
    statuses: AcademicStatusOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Courses', href: index() },
            { title: 'Edit course', href: index() },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${course.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Edit course"
            description="Update course details and order."
        />

        <Card class="max-w-2xl">
            <CardContent>
                <Form
                    v-bind="CourseController.update.form(course.id)"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="program_id">Program</Label>
                        <Select
                            name="program_id"
                            :default-value="course.program_id.toString()"
                            required
                        >
                            <SelectTrigger id="program_id" class="w-full">
                                <SelectValue />
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
                        <InputError :message="errors.program_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="course.name"
                            required
                            autofocus
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="code">Code</Label>
                            <Input
                                id="code"
                                name="code"
                                :default-value="course.code ?? ''"
                            />
                            <InputError :message="errors.code" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="slug">Slug</Label>
                            <Input
                                id="slug"
                                name="slug"
                                :default-value="course.slug"
                                required
                            />
                            <InputError :message="errors.slug" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            :default-value="course.description ?? ''"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="status">Status</Label>
                            <Select
                                name="status"
                                :default-value="course.status"
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
                                :default-value="course.sort_order"
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
