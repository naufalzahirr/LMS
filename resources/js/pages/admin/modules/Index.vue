<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { LibraryBig, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import ModuleController from '@/actions/App/Http/Controllers/Admin/ModuleController';
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
import { create, index } from '@/routes/admin/modules';
import type {
    AcademicStatus,
    AcademicStatusOption,
    CompetencyOption,
    HierarchyCourseOption,
    Paginated,
    ProgramOption,
} from '@/types/academic';

type ModuleRow = {
    id: number;
    name: string;
    competency: string;
    course: string;
    program: string;
    status: AcademicStatus;
    sort_order: number;
    lessons_count: number;
};

const props = defineProps<{
    modules: Paginated<ModuleRow>;
    filters: {
        search: string;
        program_id: string;
        course_id: string;
        competency_id: string;
        status: string;
    };
    programs: ProgramOption[];
    courses: HierarchyCourseOption[];
    competencies: CompetencyOption[];
    statuses: AcademicStatusOption[];
    canManage: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Modules', href: index() }] },
});

const page = usePage();
const filters = reactive({
    search: props.filters.search,
    program_id: props.filters.program_id || 'all',
    course_id: props.filters.course_id || 'all',
    competency_id: props.filters.competency_id || 'all',
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
const deletionErrors = computed(() => {
    const error = page.props.errors?.module;

    return typeof error === 'string' ? [error] : [];
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
    filters.status = 'all';
    applyFilters();
}

function removeModule(module: ModuleRow): void {
    if (!window.confirm(`Delete ${module.name}?`)) {
        return;
    }

    router.delete(ModuleController.destroy.url(module.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Modules" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Modules"
                description="Organize learning content beneath each competency."
            />
            <Button v-if="canManage" as-child>
                <Link :href="create()"><Plus /> Add module</Link>
            </Button>
        </div>

        <AlertError
            v-if="deletionErrors.length"
            :errors="deletionErrors"
            title="Module could not be deleted."
        />

        <form
            class="grid gap-3 rounded-lg border bg-card p-4 xl:grid-cols-[minmax(0,1fr)_11rem_12rem_14rem_10rem_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <Input
                v-model="filters.search"
                aria-label="Search modules"
                placeholder="Search module name"
            />
            <Select v-model="filters.program_id">
                <SelectTrigger aria-label="Filter by program" class="w-full"
                    ><SelectValue placeholder="All programs"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All programs</SelectItem>
                    <SelectItem
                        v-for="program in programs"
                        :key="program.id"
                        :value="program.id.toString()"
                        >{{ program.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Select v-model="filters.course_id">
                <SelectTrigger aria-label="Filter by course" class="w-full"
                    ><SelectValue placeholder="All courses"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All courses</SelectItem>
                    <SelectItem
                        v-for="course in availableCourses"
                        :key="course.id"
                        :value="course.id.toString()"
                        >{{ course.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Select v-model="filters.competency_id">
                <SelectTrigger aria-label="Filter by competency" class="w-full"
                    ><SelectValue placeholder="All competencies"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All competencies</SelectItem>
                    <SelectItem
                        v-for="competency in availableCompetencies"
                        :key="competency.id"
                        :value="competency.id.toString()"
                        >{{ competency.code }} —
                        {{ competency.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Select v-model="filters.status">
                <SelectTrigger aria-label="Filter by status" class="w-full"
                    ><SelectValue placeholder="All statuses"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All statuses</SelectItem>
                    <SelectItem
                        v-for="status in statuses"
                        :key="status.value"
                        :value="status.value"
                        >{{ status.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Button type="submit"><Search /> Filter</Button>
            <Button type="button" variant="outline" @click="resetFilters"
                >Reset</Button
            >
        </form>

        <Card class="gap-0 overflow-hidden py-0">
            <CardContent class="p-0">
                <div v-if="modules.data.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3 font-medium">Module</th>
                                <th class="px-5 py-3 font-medium">
                                    Competency
                                </th>
                                <th class="px-5 py-3 font-medium">Course</th>
                                <th class="px-5 py-3 font-medium">Program</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Order</th>
                                <th class="px-5 py-3 font-medium">Lessons</th>
                                <th
                                    v-if="canManage"
                                    class="px-5 py-3 text-right font-medium"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="module in modules.data"
                                :key="module.id"
                                class="hover:bg-muted/30"
                            >
                                <td class="px-5 py-4 font-medium">
                                    {{ module.name }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ module.competency }}
                                </td>
                                <td class="px-5 py-4">{{ module.course }}</td>
                                <td class="px-5 py-4">{{ module.program }}</td>
                                <td class="px-5 py-4">
                                    <Badge
                                        :variant="
                                            module.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        >{{ module.status }}</Badge
                                    >
                                </td>
                                <td class="px-5 py-4">
                                    {{ module.sort_order }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ module.lessons_count }}
                                </td>
                                <td v-if="canManage" class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="icon-sm"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    ModuleController.edit(
                                                        module.id,
                                                    )
                                                "
                                                :aria-label="`Edit ${module.name}`"
                                                ><Pencil
                                            /></Link>
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="icon-sm"
                                            :aria-label="`Delete ${module.name}`"
                                            @click="removeModule(module)"
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
                    <LibraryBig class="mb-4 size-10 text-muted-foreground" />
                    <p class="font-medium">No modules found</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Adjust the filters or create the first module.
                    </p>
                </div>
            </CardContent>
        </Card>

        <PaginationLinks
            :links="modules.links"
            :from="modules.from"
            :to="modules.to"
            :total="modules.total"
            item-label="modules"
        />
    </div>
</template>
