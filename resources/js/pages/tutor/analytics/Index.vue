<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    assessmentPerformanceLabel,
    metricCountLabel,
    percentageLabel,
} from '@/lib/learningAnalytics';
import type { PaginationLink } from '@/types/academic';

type Option = { id: number; name: string };
type ClassOption = Option & { course_id: number };
type Overview = {
    active_classes: number;
    active_students: number;
    completed_lessons: number;
    total_lessons: number;
    lesson_percentage: number | null;
    competencies_mastered: number;
    competencies_total: number;
    mastery_percentage: number | null;
    students_needing_remedial: number;
    remedial_cases: number;
    assessment_submitted: number;
    assessment_eligible: number;
    assessment_participation_percentage: number | null;
    assessment_graded: number;
    assessment_pending_grading: number;
    assessment_average: number | null;
};
type ClassRow = {
    id: number;
    name: string;
    course: string;
    active_students: number;
    completed_lessons: number;
    total_lessons: number;
    lesson_percentage: number | null;
    competencies_mastered: number;
    competencies_total: number;
    mastery_percentage: number | null;
    students_needing_remedial: number;
    remedial_cases: number;
    assessment_pending_grading: number;
    assessment_average: number | null;
    assessment_graded: number;
};
type StudentRow = {
    enrollment_id: number;
    student: string;
    email: string;
    class: string;
    course: string;
    completed_lessons: number;
    total_lessons: number;
    lesson_percentage: number | null;
    competencies_mastered: number;
    competencies_total: number;
    mastery_percentage: number | null;
    remedial_cases: number;
    assessment_submitted: number;
    assessment_eligible: number;
    assessment_graded: number;
    assessment_pending_grading: number;
    assessment_average: number | null;
    url: string;
};
type CompetencyRow = {
    id: number;
    competency: string;
    course: string;
    eligible_student_contexts: number;
    mastered: number;
    mastery_percentage: number | null;
    learning: number;
    students_needing_remedial: number;
    remedial_cases: number;
};
type AssessmentRow = {
    assignment_id: number;
    class: string;
    assessment: string;
    purpose: string;
    eligible_students: number;
    submitted_students: number;
    participation_percentage: number | null;
    graded_students: number;
    pending_grading_students: number;
    average_score: number | null;
    url: string;
};

const props = defineProps<{
    analytics: {
        overview: Overview;
        classes: ClassRow[];
        students: {
            data: StudentRow[];
            links: PaginationLink[];
            from: number | null;
            to: number | null;
            total: number;
        };
        competencies: CompetencyRow[];
        assessments: AssessmentRow[];
    };
    filters: { course_id: string; learning_class_id: string };
    courses: Option[];
    classes: ClassOption[];
    csvUrl: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Learning analytics', href: '/tutor/analytics' },
        ],
    },
});

const filters = reactive({
    course_id: props.filters.course_id || 'all',
    learning_class_id: props.filters.learning_class_id || 'all',
});
const availableClasses = computed(() =>
    filters.course_id === 'all'
        ? props.classes
        : props.classes.filter(
              (learningClass) =>
                  learningClass.course_id === Number(filters.course_id),
          ),
);

watch(
    () => filters.course_id,
    () => {
        if (
            filters.learning_class_id !== 'all' &&
            !availableClasses.value.some(
                (learningClass) =>
                    learningClass.id === Number(filters.learning_class_id),
            )
        ) {
            filters.learning_class_id = 'all';
        }
    },
);

function applyFilters(): void {
    router.get(
        '/tutor/analytics',
        Object.fromEntries(
            Object.entries(filters).filter(([, value]) => value !== 'all'),
        ),
        { preserveState: true, replace: true },
    );
}

function resetFilters(): void {
    filters.course_id = 'all';
    filters.learning_class_id = 'all';
    applyFilters();
}

function label(value: string): string {
    return value.replaceAll('_', ' ');
}
</script>

<template>
    <Head title="Learning Analytics" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                title="Learning analytics"
                description="Patterns and progress within your assigned active learning classes."
            />
            <Button variant="outline" as-child
                ><a :href="csvUrl"><Download /> Export Student CSV</a></Button
            >
        </div>

        <form
            class="grid gap-3 rounded-lg border bg-card p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-1.5">
                <Label for="tutor-analytics-course">Course</Label
                ><Select v-model="filters.course_id"
                    ><SelectTrigger id="tutor-analytics-course" class="w-full"
                        ><SelectValue /></SelectTrigger
                    ><SelectContent
                        ><SelectItem value="all"
                            >All assigned courses</SelectItem
                        ><SelectItem
                            v-for="item in courses"
                            :key="item.id"
                            :value="String(item.id)"
                            >{{ item.name }}</SelectItem
                        ></SelectContent
                    ></Select
                >
            </div>
            <div class="grid gap-1.5">
                <Label for="tutor-analytics-class">Learning class</Label
                ><Select v-model="filters.learning_class_id"
                    ><SelectTrigger id="tutor-analytics-class" class="w-full"
                        ><SelectValue /></SelectTrigger
                    ><SelectContent
                        ><SelectItem value="all"
                            >All assigned classes</SelectItem
                        ><SelectItem
                            v-for="item in availableClasses"
                            :key="item.id"
                            :value="String(item.id)"
                            >{{ item.name }}</SelectItem
                        ></SelectContent
                    ></Select
                >
            </div>
            <Button type="submit" class="self-end">Apply</Button
            ><Button
                type="button"
                variant="outline"
                class="self-end"
                @click="resetFilters"
                >Reset</Button
            >
        </form>

        <section class="space-y-4" aria-labelledby="tutor-overview">
            <div>
                <h2 id="tutor-overview" class="text-lg font-semibold">
                    Assigned-class overview
                </h2>
                <p class="text-sm text-muted-foreground">
                    Counts include only active enrollments in your assigned
                    active classes.
                </p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Card
                    ><CardContent
                        ><p class="text-2xl font-semibold">
                            {{ analytics.overview.active_classes }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Assigned active classes ·
                            {{ analytics.overview.active_students }} unique
                            Students
                        </p></CardContent
                    ></Card
                >
                <Card
                    ><CardContent
                        ><p class="text-2xl font-semibold">
                            {{
                                percentageLabel(
                                    analytics.overview.lesson_percentage,
                                )
                            }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ analytics.overview.completed_lessons }}/{{
                                analytics.overview.total_lessons
                            }}
                            accessible Student-lesson cells completed
                        </p></CardContent
                    ></Card
                >
                <Card
                    ><CardContent
                        ><p class="text-2xl font-semibold">
                            {{
                                percentageLabel(
                                    analytics.overview.mastery_percentage,
                                )
                            }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ analytics.overview.competencies_mastered }}/{{
                                analytics.overview.competencies_total
                            }}
                            eligible Student-competency cells mastered
                        </p></CardContent
                    ></Card
                >
                <Card
                    ><CardContent
                        ><p class="text-2xl font-semibold">
                            {{ analytics.overview.students_needing_remedial }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Unique Students needing remedial ·
                            {{ analytics.overview.remedial_cases }} cases
                        </p></CardContent
                    ></Card
                >
                <Card
                    ><CardContent
                        ><p class="text-2xl font-semibold">
                            {{ analytics.overview.assessment_pending_grading }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Student-assignment submissions pending grading
                        </p></CardContent
                    ></Card
                >
                <Card
                    ><CardContent
                        ><p class="font-semibold">
                            {{
                                assessmentPerformanceLabel(
                                    analytics.overview.assessment_average,
                                    analytics.overview.assessment_graded,
                                )
                            }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                metricCountLabel(
                                    analytics.overview
                                        .assessment_participation_percentage,
                                    analytics.overview.assessment_submitted,
                                    analytics.overview.assessment_eligible,
                                    'assignment cells submitted',
                                )
                            }}
                        </p></CardContent
                    ></Card
                >
            </div>
        </section>

        <Card>
            <CardHeader><CardTitle>Class patterns</CardTitle></CardHeader>
            <CardContent class="grid gap-3 lg:grid-cols-2">
                <Link
                    v-for="row in analytics.classes"
                    :key="row.id"
                    :href="`/tutor/reports/classes/${row.id}`"
                    class="rounded-lg border p-4 transition-colors hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ row.name }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ row.course }} ·
                                {{ row.active_students }} active Students
                            </p>
                        </div>
                        <Badge variant="outline">Open report</Badge>
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted-foreground">
                                Lesson completion
                            </dt>
                            <dd>
                                {{
                                    metricCountLabel(
                                        row.lesson_percentage,
                                        row.completed_lessons,
                                        row.total_lessons,
                                        'cells',
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Mastery</dt>
                            <dd>
                                {{
                                    metricCountLabel(
                                        row.mastery_percentage,
                                        row.competencies_mastered,
                                        row.competencies_total,
                                        'cells',
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Remedial</dt>
                            <dd>
                                {{ row.students_needing_remedial }} Students ·
                                {{ row.remedial_cases }} cases
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Assessment</dt>
                            <dd>
                                {{
                                    assessmentPerformanceLabel(
                                        row.assessment_average,
                                        row.assessment_graded,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </Link>
                <p
                    v-if="!analytics.classes.length"
                    class="text-sm text-muted-foreground"
                >
                    No assigned active classes match these filters.
                </p>
            </CardContent>
        </Card>

        <Card class="gap-0 overflow-hidden py-0">
            <CardHeader class="py-5"
                ><CardTitle>Student progress</CardTitle>
                <p class="text-sm text-muted-foreground">
                    Open the existing class progress report for full competency
                    context.
                </p></CardHeader
            >
            <CardContent class="p-0"
                ><div
                    v-if="analytics.students.data.length"
                    class="overflow-x-auto"
                >
                    <table class="w-full min-w-[76rem] text-sm">
                        <thead class="border-y bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Student</th>
                                <th class="px-5 py-3">Class</th>
                                <th class="px-5 py-3">Lessons</th>
                                <th class="px-5 py-3">Mastery</th>
                                <th class="px-5 py-3">Remedial</th>
                                <th class="px-5 py-3">Assessment status</th>
                                <th class="px-5 py-3">Performance</th>
                                <th class="px-5 py-3 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="row in analytics.students.data"
                                :key="row.enrollment_id"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium">{{ row.student }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ row.email }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    {{ row.class }}
                                    <p class="text-xs text-muted-foreground">
                                        {{ row.course }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    {{
                                        metricCountLabel(
                                            row.lesson_percentage,
                                            row.completed_lessons,
                                            row.total_lessons,
                                            'lessons',
                                        )
                                    }}
                                </td>
                                <td class="px-5 py-4">
                                    {{
                                        metricCountLabel(
                                            row.mastery_percentage,
                                            row.competencies_mastered,
                                            row.competencies_total,
                                            'competencies',
                                        )
                                    }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ row.remedial_cases }} competency cases
                                </td>
                                <td class="px-5 py-4">
                                    {{ row.assessment_submitted }}/{{
                                        row.assessment_eligible
                                    }}
                                    submitted ·
                                    {{ row.assessment_pending_grading }} pending
                                </td>
                                <td class="px-5 py-4">
                                    {{
                                        assessmentPerformanceLabel(
                                            row.assessment_average,
                                            row.assessment_graded,
                                        )
                                    }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <Button size="sm" variant="outline" as-child
                                        ><Link :href="row.url"
                                            >Open progress</Link
                                        ></Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="p-8 text-center text-sm text-muted-foreground">
                    No active Students match these filters.
                </p>
                <div class="border-t p-4">
                    <PaginationLinks
                        :links="analytics.students.links"
                        :from="analytics.students.from"
                        :to="analytics.students.to"
                        :total="analytics.students.total"
                        item-label="Student-class records"
                    /></div
            ></CardContent>
        </Card>

        <div class="grid gap-6 xl:grid-cols-2">
            <Card class="gap-0 overflow-hidden py-0"
                ><CardHeader class="py-5"
                    ><CardTitle>Competency insight</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Counts and percentages accompany status, so meaning does
                        not depend on color.
                    </p></CardHeader
                ><CardContent class="p-0"
                    ><div
                        v-if="analytics.competencies.length"
                        class="overflow-x-auto"
                    >
                        <table class="w-full min-w-[42rem] text-sm">
                            <thead class="border-y bg-muted/40 text-left">
                                <tr>
                                    <th class="px-5 py-3">Competency</th>
                                    <th class="px-5 py-3">Mastered</th>
                                    <th class="px-5 py-3">Learning</th>
                                    <th class="px-5 py-3">Remedial</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="row in analytics.competencies"
                                    :key="row.id"
                                >
                                    <td class="px-5 py-4">
                                        <p class="font-medium">
                                            {{ row.competency }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ row.course }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4">
                                        {{
                                            metricCountLabel(
                                                row.mastery_percentage,
                                                row.mastered,
                                                row.eligible_student_contexts,
                                                'eligible cells',
                                            )
                                        }}
                                    </td>
                                    <td class="px-5 py-4">
                                        {{ row.learning }}
                                    </td>
                                    <td class="px-5 py-4">
                                        {{ row.students_needing_remedial }}
                                        Students ·
                                        {{ row.remedial_cases }} cases
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p
                        v-else
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        No active competency data yet.
                    </p></CardContent
                ></Card
            >
            <Card class="gap-0 overflow-hidden py-0"
                ><CardHeader class="py-5"
                    ><CardTitle>Assessment insight</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Latest graded attempt per Student-assignment; pending
                        grades are excluded from averages.
                    </p></CardHeader
                ><CardContent class="p-0"
                    ><div
                        v-if="analytics.assessments.length"
                        class="overflow-x-auto"
                    >
                        <table class="w-full min-w-[48rem] text-sm">
                            <thead class="border-y bg-muted/40 text-left">
                                <tr>
                                    <th class="px-5 py-3">Assessment</th>
                                    <th class="px-5 py-3">Participation</th>
                                    <th class="px-5 py-3">Pending</th>
                                    <th class="px-5 py-3">Performance</th>
                                    <th class="px-5 py-3 text-right">Review</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="row in analytics.assessments"
                                    :key="row.assignment_id"
                                >
                                    <td class="px-5 py-4">
                                        <p class="font-medium">
                                            {{ row.assessment }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ row.class }} ·
                                            {{ label(row.purpose) }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4">
                                        {{
                                            metricCountLabel(
                                                row.participation_percentage,
                                                row.submitted_students,
                                                row.eligible_students,
                                                'Students',
                                            )
                                        }}
                                    </td>
                                    <td class="px-5 py-4">
                                        {{ row.pending_grading_students }}
                                    </td>
                                    <td class="px-5 py-4">
                                        {{
                                            assessmentPerformanceLabel(
                                                row.average_score,
                                                row.graded_students,
                                            )
                                        }}
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            as-child
                                            ><Link :href="row.url"
                                                >Open attempts</Link
                                            ></Button
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p
                        v-else
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        No active assessment assignments in this scope.
                    </p></CardContent
                ></Card
            >
        </div>
    </div>
</template>
