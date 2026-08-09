<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { BookOpenCheck, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, reactive } from 'vue';
import CourseController from '@/actions/App/Http/Controllers/Admin/CourseController';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, index } from '@/routes/admin/courses';
import type {
    AcademicStatus,
    AcademicStatusOption,
    Paginated,
    ProgramOption,
} from '@/types/academic';

type CourseRow = {
    id: number;
    name: string;
    program: string;
    code: string | null;
    competencies_count: number;
    status: AcademicStatus;
};

const props = defineProps<{
    courses: Paginated<CourseRow>;
    filters: { search: string; program_id: string; status: string };
    programs: ProgramOption[];
    statuses: AcademicStatusOption[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Courses', href: index() }],
    },
});

const page = usePage();
const filters = reactive({
    search: props.filters.search,
    program_id: props.filters.program_id || 'all',
    status: props.filters.status || 'all',
});
const deletionErrors = computed(() => {
    const error = page.props.errors?.course;

    return typeof error === 'string' ? [error] : [];
});

function applyFilters(): void {
    router.get(
        index.url(),
        {
            search: filters.search || undefined,
            program_id:
                filters.program_id === 'all' ? undefined : filters.program_id,
            status: filters.status === 'all' ? undefined : filters.status,
        },
        { preserveState: true, replace: true },
    );
}

function resetFilters(): void {
    filters.search = '';
    filters.program_id = 'all';
    filters.status = 'all';
    applyFilters();
}

function removeCourse(course: CourseRow): void {
    if (!window.confirm(`Delete ${course.name}?`)) {
        return;
    }

    router.delete(CourseController.destroy.url(course.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Courses" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Courses"
                description="Organize courses within academic programs."
            />
            <Button v-if="canManage" as-child>
                <Link :href="create()"><Plus /> Add course</Link>
            </Button>
        </div>

        <AlertError
            v-if="deletionErrors.length"
            :errors="deletionErrors"
            title="Course could not be deleted."
        />

        <form
            class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-[minmax(0,1fr)_13rem_11rem_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <Input
                v-model="filters.search"
                aria-label="Search courses"
                placeholder="Search name or code"
            />
            <Select v-model="filters.program_id">
                <SelectTrigger aria-label="Filter by program" class="w-full">
                    <SelectValue placeholder="All programs" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All programs</SelectItem>
                    <SelectItem
                        v-for="program in programs"
                        :key="program.id"
                        :value="program.id.toString()"
                    >
                        {{ program.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="filters.status">
                <SelectTrigger aria-label="Filter by status" class="w-full">
                    <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All statuses</SelectItem>
                    <SelectItem
                        v-for="status in statuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Button type="submit"><Search /> Filter</Button>
            <Button type="button" variant="outline" @click="resetFilters">
                Reset
            </Button>
        </form>

        <Card class="gap-0 overflow-hidden py-0">
            <CardContent class="p-0">
                <div v-if="courses.data.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-6 py-3 font-medium">Course</th>
                                <th class="px-6 py-3 font-medium">Program</th>
                                <th class="px-6 py-3 font-medium">Code</th>
                                <th class="px-6 py-3 font-medium">
                                    Competencies
                                </th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th
                                    v-if="canManage"
                                    class="px-6 py-3 text-right font-medium"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="course in courses.data"
                                :key="course.id"
                                class="transition-colors hover:bg-muted/30"
                            >
                                <td class="px-6 py-4 font-medium">
                                    {{ course.name }}
                                </td>
                                <td class="px-6 py-4">{{ course.program }}</td>
                                <td class="px-6 py-4 text-muted-foreground">
                                    {{ course.code ?? '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    {{ course.competencies_count }}
                                </td>
                                <td class="px-6 py-4">
                                    <Badge
                                        :variant="
                                            course.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{ course.status }}
                                    </Badge>
                                </td>
                                <td v-if="canManage" class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="icon-sm"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    CourseController.edit(
                                                        course.id,
                                                    )
                                                "
                                                :aria-label="`Edit ${course.name}`"
                                            >
                                                <Pencil />
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="icon-sm"
                                            :aria-label="`Delete ${course.name}`"
                                            @click="removeCourse(course)"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-else
                    class="flex flex-col items-center px-6 py-16 text-center"
                >
                    <BookOpenCheck class="mb-4 size-10 text-muted-foreground" />
                    <p class="font-medium">No courses found</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Adjust the filters or create the first course.
                    </p>
                </div>
            </CardContent>
        </Card>

        <PaginationLinks
            :links="courses.links"
            :from="courses.from"
            :to="courses.to"
            :total="courses.total"
            item-label="courses"
        />
    </div>
</template>
