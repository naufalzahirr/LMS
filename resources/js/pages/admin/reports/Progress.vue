<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Search } from '@lucide/vue';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Option = { id: number; name: string };
type CourseOption = Option & { program_id: number };
type ClassOption = Option & { course_id: number };
type StudentOption = Option & { email: string };
type ReportRow = {
    enrollment_id: number;
    student: string;
    email: string;
    program: string;
    course: string;
    class: string;
    enrollment_status: string;
    completed_lessons: number;
    total_lessons: number;
    lesson_percentage: number;
    competencies_mastered: number;
    competencies_total: number;
    needs_remedial: number;
    average_best_score: number | null;
    url: string;
};

const props = defineProps<{
    report: {
        rows: ReportRow[];
        summary: {
            students: number;
            classes: number;
            enrollments: number;
            competencies_mastered: number;
            needs_remedial: number;
            average_best_score: number;
        };
    };
    filters: {
        program_id: string;
        course_id: string;
        learning_class_id: string;
        student_id: string;
        mastery_status: string;
    };
    programs: Option[];
    courses: CourseOption[];
    classes: ClassOption[];
    students: StudentOption[];
    masteryStatuses: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Progress reports', href: '/admin/reports/progress' },
        ],
    },
});

const filters = reactive({
    program_id: props.filters.program_id || 'all',
    course_id: props.filters.course_id || 'all',
    learning_class_id: props.filters.learning_class_id || 'all',
    student_id: props.filters.student_id || 'all',
    mastery_status: props.filters.mastery_status || 'all',
});
const availableCourses = computed(() =>
    filters.program_id === 'all'
        ? props.courses
        : props.courses.filter(
              (course) => course.program_id === Number(filters.program_id),
          ),
);
const availableClasses = computed(() =>
    filters.course_id === 'all'
        ? props.classes
        : props.classes.filter(
              (learningClass) =>
                  learningClass.course_id === Number(filters.course_id),
          ),
);

function label(value: string): string {
    return value.replaceAll('_', ' ');
}

function applyFilters(): void {
    router.get(
        '/admin/reports/progress',
        {
            program_id:
                filters.program_id === 'all' ? undefined : filters.program_id,
            course_id:
                filters.course_id === 'all' ? undefined : filters.course_id,
            learning_class_id:
                filters.learning_class_id === 'all'
                    ? undefined
                    : filters.learning_class_id,
            student_id:
                filters.student_id === 'all' ? undefined : filters.student_id,
            mastery_status:
                filters.mastery_status === 'all'
                    ? undefined
                    : filters.mastery_status,
        },
        { preserveState: true, replace: true },
    );
}

function resetFilters(): void {
    filters.program_id = 'all';
    filters.course_id = 'all';
    filters.learning_class_id = 'all';
    filters.student_id = 'all';
    filters.mastery_status = 'all';
    applyFilters();
}
</script>

<template>
    <Head title="Progress Reports" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Progress reports"
            description="Learning completion, competency mastery, and intervention signals across classes."
        />

        <form
            class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-2 xl:grid-cols-[repeat(5,minmax(0,1fr))_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <Select v-model="filters.program_id">
                <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All programs</SelectItem>
                    <SelectItem
                        v-for="program in programs"
                        :key="program.id"
                        :value="String(program.id)"
                    >
                        {{ program.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="filters.course_id">
                <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All courses</SelectItem>
                    <SelectItem
                        v-for="course in availableCourses"
                        :key="course.id"
                        :value="String(course.id)"
                    >
                        {{ course.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="filters.learning_class_id">
                <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All classes</SelectItem>
                    <SelectItem
                        v-for="learningClass in availableClasses"
                        :key="learningClass.id"
                        :value="String(learningClass.id)"
                    >
                        {{ learningClass.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="filters.student_id">
                <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All students</SelectItem>
                    <SelectItem
                        v-for="student in students"
                        :key="student.id"
                        :value="String(student.id)"
                    >
                        {{ student.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="filters.mastery_status">
                <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All mastery statuses</SelectItem>
                    <SelectItem
                        v-for="status in masteryStatuses"
                        :key="status"
                        :value="status"
                        class="capitalize"
                    >
                        {{ label(status) }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Button type="submit"><Search /> Filter</Button>
            <Button type="button" variant="outline" @click="resetFilters"
                >Reset</Button
            >
        </form>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <Card>
                <CardContent
                    ><p class="text-2xl font-semibold">
                        {{ report.summary.students }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Students
                    </p></CardContent
                >
            </Card>
            <Card>
                <CardContent
                    ><p class="text-2xl font-semibold">
                        {{ report.summary.classes }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Classes
                    </p></CardContent
                >
            </Card>
            <Card>
                <CardContent
                    ><p class="text-2xl font-semibold">
                        {{ report.summary.competencies_mastered }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Mastered cells
                    </p></CardContent
                >
            </Card>
            <Card>
                <CardContent
                    ><p class="text-2xl font-semibold">
                        {{ report.summary.needs_remedial }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Needs remedial
                    </p></CardContent
                >
            </Card>
            <Card>
                <CardContent
                    ><p class="text-2xl font-semibold">
                        {{ report.summary.average_best_score }}%
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Average best score
                    </p></CardContent
                >
            </Card>
        </div>

        <Card class="gap-0 overflow-hidden py-0">
            <CardContent class="p-0">
                <div v-if="report.rows.length" class="overflow-x-auto">
                    <table class="w-full min-w-[70rem] text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Student</th>
                                <th class="px-5 py-3">Class</th>
                                <th class="px-5 py-3">Enrollment</th>
                                <th class="px-5 py-3">Lessons</th>
                                <th class="px-5 py-3">Mastery</th>
                                <th class="px-5 py-3">Needs remedial</th>
                                <th class="px-5 py-3">Avg. best</th>
                                <th class="px-5 py-3 text-right">Report</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="row in report.rows"
                                :key="row.enrollment_id"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium">{{ row.student }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ row.email }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <p>{{ row.class }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ row.program }} · {{ row.course }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 capitalize">
                                    <Badge variant="outline">{{
                                        label(row.enrollment_status)
                                    }}</Badge>
                                </td>
                                <td class="px-5 py-4">
                                    {{ row.completed_lessons }} /
                                    {{ row.total_lessons }} ·
                                    {{ row.lesson_percentage }}%
                                </td>
                                <td class="px-5 py-4">
                                    {{ row.competencies_mastered }} /
                                    {{ row.competencies_total }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ row.needs_remedial }}
                                </td>
                                <td class="px-5 py-4">
                                    {{
                                        row.average_best_score === null
                                            ? '—'
                                            : `${row.average_best_score}%`
                                    }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <Button size="sm" variant="outline" as-child
                                        ><Link :href="row.url"
                                            ><Eye /> Open</Link
                                        ></Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    v-else
                    class="p-10 text-center text-sm text-muted-foreground"
                >
                    No enrollments match these filters.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
