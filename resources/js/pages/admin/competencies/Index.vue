<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Target, Trash2 } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import CompetencyController from '@/actions/App/Http/Controllers/Admin/CompetencyController';
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
import { create, index } from '@/routes/admin/competencies';
import type {
    AcademicStatus,
    AcademicStatusOption,
    CourseOption,
    Paginated,
    ProgramOption,
} from '@/types/academic';

type CompetencyRow = {
    id: number;
    code: string;
    name: string;
    course: string;
    program: string;
    status: AcademicStatus;
    sort_order: number;
};

const props = defineProps<{
    competencies: Paginated<CompetencyRow>;
    filters: {
        search: string;
        program_id: string;
        course_id: string;
        status: string;
    };
    programs: ProgramOption[];
    courses: CourseOption[];
    statuses: AcademicStatusOption[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Competencies', href: index() }],
    },
});

const filters = reactive({
    search: props.filters.search,
    program_id: props.filters.program_id || 'all',
    course_id: props.filters.course_id || 'all',
    status: props.filters.status || 'all',
});
const availableCourses = computed(() => {
    if (filters.program_id === 'all') {
        return props.courses;
    }

    return props.courses.filter(
        (course) => course.program_id === Number(filters.program_id),
    );
});

watch(
    () => filters.program_id,
    () => {
        if (
            filters.course_id !== 'all' &&
            !availableCourses.value.some(
                (course) => course.id === Number(filters.course_id),
            )
        ) {
            filters.course_id = 'all';
        }
    },
);

function applyFilters(): void {
    router.get(
        index.url(),
        {
            search: filters.search || undefined,
            program_id:
                filters.program_id === 'all' ? undefined : filters.program_id,
            course_id:
                filters.course_id === 'all' ? undefined : filters.course_id,
            status: filters.status === 'all' ? undefined : filters.status,
        },
        { preserveState: true, replace: true },
    );
}

function resetFilters(): void {
    filters.search = '';
    filters.program_id = 'all';
    filters.course_id = 'all';
    filters.status = 'all';
    applyFilters();
}

function removeCompetency(competency: CompetencyRow): void {
    if (!window.confirm(`Delete ${competency.code} — ${competency.name}?`)) {
        return;
    }

    router.delete(CompetencyController.destroy.url(competency.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Competencies" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Competencies"
                description="Define the measurable skills within each course."
            />
            <Button v-if="canManage" as-child>
                <Link :href="create()"><Plus /> Add competency</Link>
            </Button>
        </div>

        <form
            class="grid gap-3 rounded-lg border bg-card p-4 lg:grid-cols-[minmax(0,1fr)_12rem_14rem_10rem_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <Input
                v-model="filters.search"
                aria-label="Search competencies"
                placeholder="Search code or name"
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
            <Select v-model="filters.course_id">
                <SelectTrigger aria-label="Filter by course" class="w-full">
                    <SelectValue placeholder="All courses" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All courses</SelectItem>
                    <SelectItem
                        v-for="course in availableCourses"
                        :key="course.id"
                        :value="course.id.toString()"
                    >
                        {{ course.name }}
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
                <div v-if="competencies.data.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-6 py-3 font-medium">Code</th>
                                <th class="px-6 py-3 font-medium">
                                    Competency
                                </th>
                                <th class="px-6 py-3 font-medium">Course</th>
                                <th class="px-6 py-3 font-medium">Program</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium">Order</th>
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
                                v-for="competency in competencies.data"
                                :key="competency.id"
                                class="transition-colors hover:bg-muted/30"
                            >
                                <td
                                    class="px-6 py-4 font-mono text-xs font-medium"
                                >
                                    {{ competency.code }}
                                </td>
                                <td class="px-6 py-4 font-medium">
                                    {{ competency.name }}
                                </td>
                                <td class="px-6 py-4">
                                    {{ competency.course }}
                                </td>
                                <td class="px-6 py-4">
                                    {{ competency.program }}
                                </td>
                                <td class="px-6 py-4">
                                    <Badge
                                        :variant="
                                            competency.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{ competency.status }}
                                    </Badge>
                                </td>
                                <td class="px-6 py-4">
                                    {{ competency.sort_order }}
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
                                                    CompetencyController.edit(
                                                        competency.id,
                                                    )
                                                "
                                                :aria-label="`Edit ${competency.name}`"
                                            >
                                                <Pencil />
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="icon-sm"
                                            :aria-label="`Delete ${competency.name}`"
                                            @click="
                                                removeCompetency(competency)
                                            "
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
                    <Target class="mb-4 size-10 text-muted-foreground" />
                    <p class="font-medium">No competencies found</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Adjust the filters or create the first competency.
                    </p>
                </div>
            </CardContent>
        </Card>

        <PaginationLinks
            :links="competencies.links"
            :from="competencies.from"
            :to="competencies.to"
            :total="competencies.total"
            item-label="competencies"
        />
    </div>
</template>
