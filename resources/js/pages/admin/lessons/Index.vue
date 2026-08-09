<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, NotebookText, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import LessonController from '@/actions/App/Http/Controllers/Admin/LessonController';
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
import { create, index, show } from '@/routes/admin/lessons';
import type {
    AcademicStatus,
    AcademicStatusOption,
    CompetencyOption,
    HierarchyCourseOption,
    LessonType,
    LessonTypeOption,
    ModuleOption,
    Paginated,
    ProgramOption,
} from '@/types/academic';

type LessonRow = {
    id: number;
    title: string;
    lesson_type: LessonType;
    module: string;
    competency: string;
    course: string;
    status: AcademicStatus;
    duration_minutes: number | null;
    sort_order: number;
};

const props = defineProps<{
    lessons: Paginated<LessonRow>;
    filters: {
        search: string;
        program_id: string;
        course_id: string;
        competency_id: string;
        module_id: string;
        lesson_type: string;
        status: string;
    };
    programs: ProgramOption[];
    courses: HierarchyCourseOption[];
    competencies: CompetencyOption[];
    modules: ModuleOption[];
    lessonTypes: LessonTypeOption[];
    statuses: AcademicStatusOption[];
    canManage: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Lessons', href: index() }] },
});

const filters = reactive({
    search: props.filters.search,
    program_id: props.filters.program_id || 'all',
    course_id: props.filters.course_id || 'all',
    competency_id: props.filters.competency_id || 'all',
    module_id: props.filters.module_id || 'all',
    lesson_type: props.filters.lesson_type || 'all',
    status: props.filters.status || 'all',
});
const availableCourses = computed(() =>
    filters.program_id === 'all'
        ? props.courses
        : props.courses.filter(
              (course) => course.program_id === Number(filters.program_id),
          ),
);
const availableCompetencies = computed(() =>
    filters.course_id === 'all'
        ? props.competencies
        : props.competencies.filter(
              (competency) =>
                  competency.course_id === Number(filters.course_id),
          ),
);
const availableModules = computed(() =>
    filters.competency_id === 'all'
        ? props.modules
        : props.modules.filter(
              (module) =>
                  module.competency_id === Number(filters.competency_id),
          ),
);

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
watch(
    () => filters.course_id,
    () => {
        if (
            filters.competency_id !== 'all' &&
            !availableCompetencies.value.some(
                (competency) => competency.id === Number(filters.competency_id),
            )
        ) {
            filters.competency_id = 'all';
        }
    },
);
watch(
    () => filters.competency_id,
    () => {
        if (
            filters.module_id !== 'all' &&
            !availableModules.value.some(
                (module) => module.id === Number(filters.module_id),
            )
        ) {
            filters.module_id = 'all';
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
            competency_id:
                filters.competency_id === 'all'
                    ? undefined
                    : filters.competency_id,
            module_id:
                filters.module_id === 'all' ? undefined : filters.module_id,
            lesson_type:
                filters.lesson_type === 'all' ? undefined : filters.lesson_type,
            status: filters.status === 'all' ? undefined : filters.status,
        },
        { preserveState: true, replace: true },
    );
}

function resetFilters(): void {
    filters.search = '';
    filters.program_id = 'all';
    filters.course_id = 'all';
    filters.competency_id = 'all';
    filters.module_id = 'all';
    filters.lesson_type = 'all';
    filters.status = 'all';
    applyFilters();
}

function removeLesson(lesson: LessonRow): void {
    if (!window.confirm(`Delete ${lesson.title}?`)) {
        return;
    }

    router.delete(LessonController.destroy.url(lesson.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Lessons" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Lessons"
                description="Manage reusable content within learning modules."
            />
            <Button v-if="canManage" as-child
                ><Link :href="create()"><Plus /> Add lesson</Link></Button
            >
        </div>

        <form
            class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-2 xl:grid-cols-4"
            @submit.prevent="applyFilters"
        >
            <Input
                v-model="filters.search"
                aria-label="Search lessons"
                placeholder="Search lesson title"
            />
            <Select v-model="filters.program_id">
                <SelectTrigger aria-label="Filter by program" class="w-full"
                    ><SelectValue placeholder="All programs"
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem value="all">All programs</SelectItem
                    ><SelectItem
                        v-for="program in programs"
                        :key="program.id"
                        :value="program.id.toString()"
                        >{{ program.name }}</SelectItem
                    ></SelectContent
                >
            </Select>
            <Select v-model="filters.course_id">
                <SelectTrigger aria-label="Filter by course" class="w-full"
                    ><SelectValue placeholder="All courses"
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem value="all">All courses</SelectItem
                    ><SelectItem
                        v-for="course in availableCourses"
                        :key="course.id"
                        :value="course.id.toString()"
                        >{{ course.name }}</SelectItem
                    ></SelectContent
                >
            </Select>
            <Select v-model="filters.competency_id">
                <SelectTrigger aria-label="Filter by competency" class="w-full"
                    ><SelectValue placeholder="All competencies"
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem value="all">All competencies</SelectItem
                    ><SelectItem
                        v-for="competency in availableCompetencies"
                        :key="competency.id"
                        :value="competency.id.toString()"
                        >{{ competency.code }} —
                        {{ competency.name }}</SelectItem
                    ></SelectContent
                >
            </Select>
            <Select v-model="filters.module_id">
                <SelectTrigger aria-label="Filter by module" class="w-full"
                    ><SelectValue placeholder="All modules"
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem value="all">All modules</SelectItem
                    ><SelectItem
                        v-for="module in availableModules"
                        :key="module.id"
                        :value="module.id.toString()"
                        >{{ module.name }}</SelectItem
                    ></SelectContent
                >
            </Select>
            <Select v-model="filters.lesson_type">
                <SelectTrigger aria-label="Filter by lesson type" class="w-full"
                    ><SelectValue placeholder="All types"
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem value="all">All types</SelectItem
                    ><SelectItem
                        v-for="type in lessonTypes"
                        :key="type.value"
                        :value="type.value"
                        >{{ type.label }}</SelectItem
                    ></SelectContent
                >
            </Select>
            <Select v-model="filters.status">
                <SelectTrigger aria-label="Filter by status" class="w-full"
                    ><SelectValue placeholder="All statuses"
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem value="all">All statuses</SelectItem
                    ><SelectItem
                        v-for="status in statuses"
                        :key="status.value"
                        :value="status.value"
                        >{{ status.label }}</SelectItem
                    ></SelectContent
                >
            </Select>
            <div class="flex gap-2">
                <Button type="submit" class="flex-1"><Search /> Filter</Button
                ><Button type="button" variant="outline" @click="resetFilters"
                    >Reset</Button
                >
            </div>
        </form>

        <Card class="gap-0 overflow-hidden py-0">
            <CardContent class="p-0">
                <div v-if="lessons.data.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3 font-medium">Lesson</th>
                                <th class="px-5 py-3 font-medium">Type</th>
                                <th class="px-5 py-3 font-medium">Module</th>
                                <th class="px-5 py-3 font-medium">
                                    Competency
                                </th>
                                <th class="px-5 py-3 font-medium">Course</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Duration</th>
                                <th class="px-5 py-3 font-medium">Order</th>
                                <th class="px-5 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="lesson in lessons.data"
                                :key="lesson.id"
                                class="hover:bg-muted/30"
                            >
                                <td class="px-5 py-4 font-medium">
                                    {{ lesson.title }}
                                </td>
                                <td class="px-5 py-4">
                                    <Badge variant="outline">{{
                                        lesson.lesson_type
                                    }}</Badge>
                                </td>
                                <td class="px-5 py-4">{{ lesson.module }}</td>
                                <td class="px-5 py-4">
                                    {{ lesson.competency }}
                                </td>
                                <td class="px-5 py-4">{{ lesson.course }}</td>
                                <td class="px-5 py-4">
                                    <Badge
                                        :variant="
                                            lesson.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        >{{ lesson.status }}</Badge
                                    >
                                </td>
                                <td class="px-5 py-4">
                                    {{
                                        lesson.duration_minutes === null
                                            ? '—'
                                            : `${lesson.duration_minutes} min`
                                    }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ lesson.sort_order }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="icon-sm"
                                            as-child
                                            ><Link
                                                :href="show(lesson.id)"
                                                :aria-label="`Preview ${lesson.title}`"
                                                ><Eye /></Link
                                        ></Button>
                                        <Button
                                            v-if="canManage"
                                            variant="outline"
                                            size="icon-sm"
                                            as-child
                                            ><Link
                                                :href="
                                                    LessonController.edit(
                                                        lesson.id,
                                                    )
                                                "
                                                :aria-label="`Edit ${lesson.title}`"
                                                ><Pencil /></Link
                                        ></Button>
                                        <Button
                                            v-if="canManage"
                                            variant="destructive"
                                            size="icon-sm"
                                            :aria-label="`Delete ${lesson.title}`"
                                            @click="removeLesson(lesson)"
                                            ><Trash2
                                        /></Button>
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
                    <NotebookText class="mb-4 size-10 text-muted-foreground" />
                    <p class="font-medium">No lessons found</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Adjust the filters or create the first lesson.
                    </p>
                </div>
            </CardContent>
        </Card>
        <PaginationLinks
            :links="lessons.links"
            :from="lessons.from"
            :to="lessons.to"
            :total="lessons.total"
            item-label="lessons"
        />
    </div>
</template>
