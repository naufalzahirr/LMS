<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BookOpenCheck,
    ChartNoAxesCombined,
    ClipboardCheck,
    Download,
    Target,
    UsersRound,
} from '@lucide/vue';
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
type CourseOption = Option & { program_id: number };
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
    code: string;
    program: string;
    course: string;
    tutors: string[];
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
    class_id: number;
    class: string;
    assessment: string;
    purpose: string;
    eligible_students: number;
    submitted_students: number;
    participation_percentage: number | null;
    graded_students: number;
    pending_grading_students: number;
    average_score: number | null;
};

const props = defineProps<{
    analytics: {
        overview: Overview;
        classes: {
            data: ClassRow[];
            links: PaginationLink[];
            from: number | null;
            to: number | null;
            total: number;
        };
        competencies: CompetencyRow[];
        remedial_concentration: ClassRow[];
        assessments: AssessmentRow[];
    };
    filters: {
        program_id: string;
        course_id: string;
        learning_class_id: string;
        tutor_id: string;
    };
    programs: Option[];
    courses: CourseOption[];
    classes: ClassOption[];
    tutors: Option[];
    csvUrl: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Learning analytics', href: '/admin/analytics' },
        ],
    },
});

const filters = reactive({
    program_id: props.filters.program_id || 'all',
    course_id: props.filters.course_id || 'all',
    learning_class_id: props.filters.learning_class_id || 'all',
    tutor_id: props.filters.tutor_id || 'all',
});
const availableCourses = computed(() =>
    filters.program_id === 'all'
        ? props.courses
        : props.courses.filter(
              (course) => course.program_id === Number(filters.program_id),
          ),
);
const availableClasses = computed(() => {
    const allowedCourseIds = new Set(
        availableCourses.value.map((course) => course.id),
    );

    return props.classes.filter(
        (learningClass) =>
            (filters.course_id === 'all' &&
                allowedCourseIds.has(learningClass.course_id)) ||
            learningClass.course_id === Number(filters.course_id),
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
        '/admin/analytics',
        Object.fromEntries(
            Object.entries(filters)
                .filter(([, value]) => value !== 'all')
                .map(([key, value]) => [key, value]),
        ),
        { preserveState: true, replace: true },
    );
}

function resetFilters(): void {
    filters.program_id = 'all';
    filters.course_id = 'all';
    filters.learning_class_id = 'all';
    filters.tutor_id = 'all';
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
                description="Current learning activity and outcomes across active programs, courses, and classes."
            />
            <Button variant="outline" as-child>
                <a :href="csvUrl"><Download /> Export class CSV</a>
            </Button>
        </div>

        <form
            class="grid gap-3 rounded-lg border bg-card p-4 sm:grid-cols-2 xl:grid-cols-[repeat(4,minmax(0,1fr))_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-1.5">
                <Label for="analytics-program">Program</Label>
                <Select v-model="filters.program_id">
                    <SelectTrigger id="analytics-program" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All active programs</SelectItem>
                        <SelectItem
                            v-for="item in programs"
                            :key="item.id"
                            :value="String(item.id)"
                            >{{ item.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label for="analytics-course">Course</Label>
                <Select v-model="filters.course_id">
                    <SelectTrigger id="analytics-course" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All active courses</SelectItem>
                        <SelectItem
                            v-for="item in availableCourses"
                            :key="item.id"
                            :value="String(item.id)"
                            >{{ item.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label for="analytics-class">Learning class</Label>
                <Select v-model="filters.learning_class_id">
                    <SelectTrigger id="analytics-class" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All active classes</SelectItem>
                        <SelectItem
                            v-for="item in availableClasses"
                            :key="item.id"
                            :value="String(item.id)"
                            >{{ item.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label for="analytics-tutor">Tutor</Label>
                <Select v-model="filters.tutor_id">
                    <SelectTrigger id="analytics-tutor" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All tutors</SelectItem>
                        <SelectItem
                            v-for="item in tutors"
                            :key="item.id"
                            :value="String(item.id)"
                            >{{ item.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <Button type="submit" class="self-end">Apply</Button>
            <Button
                type="button"
                variant="outline"
                class="self-end"
                @click="resetFilters"
                >Reset</Button
            >
        </form>

        <section aria-labelledby="analytics-overview" class="space-y-4">
            <div>
                <h2 id="analytics-overview" class="text-lg font-semibold">
                    Current-learning overview
                </h2>
                <p class="text-sm text-muted-foreground">
                    Only active classes, enrollments, programs, courses,
                    competencies, lessons, and assessment assignments are
                    included.
                </p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Card
                    ><CardContent class="flex items-center gap-4"
                        ><ChartNoAxesCombined
                            class="size-8 text-muted-foreground"
                        />
                        <div>
                            <p class="text-2xl font-semibold">
                                {{ analytics.overview.active_classes }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Active learning classes
                            </p>
                        </div></CardContent
                    ></Card
                >
                <Card
                    ><CardContent class="flex items-center gap-4"
                        ><UsersRound class="size-8 text-muted-foreground" />
                        <div>
                            <p class="text-2xl font-semibold">
                                {{ analytics.overview.active_students }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Unique active Students
                            </p>
                        </div></CardContent
                    ></Card
                >
                <Card
                    ><CardContent class="flex items-center gap-4"
                        ><BookOpenCheck class="size-8 text-muted-foreground" />
                        <div>
                            <p class="text-2xl font-semibold">
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
                            </p>
                        </div></CardContent
                    ></Card
                >
                <Card
                    ><CardContent class="flex items-center gap-4"
                        ><Target class="size-8 text-muted-foreground" />
                        <div>
                            <p class="text-2xl font-semibold">
                                {{
                                    percentageLabel(
                                        analytics.overview.mastery_percentage,
                                    )
                                }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    analytics.overview.competencies_mastered
                                }}/{{ analytics.overview.competencies_total }}
                                eligible Student-competency cells mastered
                            </p>
                        </div></CardContent
                    ></Card
                >
                <Card
                    ><CardContent
                        ><p class="text-2xl font-semibold">
                            {{ analytics.overview.students_needing_remedial }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Unique Students needing remedial ·
                            {{ analytics.overview.remedial_cases }} competency
                            cases
                        </p></CardContent
                    ></Card
                >
                <Card
                    ><CardContent class="flex items-center gap-4"
                        ><ClipboardCheck class="size-8 text-muted-foreground" />
                        <div>
                            <p class="font-semibold">
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
                                        'Student-assignment cells submitted',
                                    )
                                }}
                            </p>
                        </div></CardContent
                    ></Card
                >
            </div>
        </section>

        <Card class="gap-0 overflow-hidden py-0">
            <CardHeader class="py-5"
                ><CardTitle>Class performance</CardTitle>
                <p class="text-sm text-muted-foreground">
                    Comparable learning outcomes with numerator and denominator
                    context. Tutor names identify scope; classes are not ranked
                    as personnel.
                </p></CardHeader
            >
            <CardContent class="p-0">
                <div
                    v-if="analytics.classes.data.length"
                    class="overflow-x-auto"
                >
                    <table class="w-full min-w-[82rem] text-sm">
                        <thead class="border-y bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Class</th>
                                <th class="px-5 py-3">Tutor</th>
                                <th class="px-5 py-3">Active Students</th>
                                <th class="px-5 py-3">Lesson completion</th>
                                <th class="px-5 py-3">Competency mastery</th>
                                <th class="px-5 py-3">Remedial</th>
                                <th class="px-5 py-3">
                                    Assessment participation
                                </th>
                                <th class="px-5 py-3">
                                    Assessment performance
                                </th>
                                <th class="px-5 py-3 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="row in analytics.classes.data"
                                :key="row.id"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium">
                                        {{ row.name }} · {{ row.code }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ row.program }} / {{ row.course }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    {{
                                        row.tutors.join(', ') || 'Not assigned'
                                    }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ row.active_students }}
                                </td>
                                <td class="px-5 py-4">
                                    {{
                                        metricCountLabel(
                                            row.lesson_percentage,
                                            row.completed_lessons,
                                            row.total_lessons,
                                            'lesson cells',
                                        )
                                    }}
                                </td>
                                <td class="px-5 py-4">
                                    {{
                                        metricCountLabel(
                                            row.mastery_percentage,
                                            row.competencies_mastered,
                                            row.competencies_total,
                                            'competency cells',
                                        )
                                    }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ row.students_needing_remedial }} Students
                                    · {{ row.remedial_cases }} cases
                                </td>
                                <td class="px-5 py-4">
                                    {{
                                        metricCountLabel(
                                            row.assessment_participation_percentage,
                                            row.assessment_submitted,
                                            row.assessment_eligible,
                                            'assignment cells',
                                        )
                                    }}
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
                                        ><Link
                                            :href="`/admin/reports/classes/${row.id}`"
                                            >Open report</Link
                                        ></Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="p-8 text-center text-sm text-muted-foreground">
                    No active classes match these filters.
                </p>
                <div class="border-t p-4">
                    <PaginationLinks
                        :links="analytics.classes.links"
                        :from="analytics.classes.from"
                        :to="analytics.classes.to"
                        :total="analytics.classes.total"
                        item-label="classes"
                    />
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 xl:grid-cols-2">
            <Card class="gap-0 overflow-hidden py-0">
                <CardHeader class="py-5"
                    ><CardTitle>Competencies needing support</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Lowest current mastery first. Population is eligible
                        Student-class competency cells.
                    </p></CardHeader
                >
                <CardContent class="p-0"
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
                >
            </Card>
            <Card>
                <CardHeader
                    ><CardTitle>Remedial concentration</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Observable remedial states, not a predictive risk score.
                    </p></CardHeader
                >
                <CardContent class="space-y-3"
                    ><div
                        v-for="row in analytics.remedial_concentration"
                        :key="row.id"
                        class="flex flex-col gap-2 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="font-medium">{{ row.name }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ row.course }}
                            </p>
                        </div>
                        <div class="text-sm sm:text-right">
                            <p>
                                {{ row.students_needing_remedial }} of
                                {{ row.active_students }} Students
                            </p>
                            <p class="text-muted-foreground">
                                {{ row.remedial_cases }} competency cases
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="!analytics.remedial_concentration.length"
                        class="text-sm text-muted-foreground"
                    >
                        No Students currently need remedial support in this
                        scope.
                    </p></CardContent
                >
            </Card>
        </div>

        <Card class="gap-0 overflow-hidden py-0">
            <CardHeader class="py-5"
                ><CardTitle>Assessment insight</CardTitle>
                <p class="text-sm text-muted-foreground">
                    Participation counts each Student once per assignment.
                    Performance uses the latest graded attempt per
                    Student-assignment.
                </p></CardHeader
            >
            <CardContent class="p-0"
                ><div
                    v-if="analytics.assessments.length"
                    class="overflow-x-auto"
                >
                    <table class="w-full min-w-[64rem] text-sm">
                        <thead class="border-y bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Assessment</th>
                                <th class="px-5 py-3">Class</th>
                                <th class="px-5 py-3">Eligible</th>
                                <th class="px-5 py-3">Submitted</th>
                                <th class="px-5 py-3">Pending grading</th>
                                <th class="px-5 py-3">Performance</th>
                                <th class="px-5 py-3">Purpose</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="row in analytics.assessments"
                                :key="row.assignment_id"
                            >
                                <td class="px-5 py-4 font-medium">
                                    {{ row.assessment }}
                                </td>
                                <td class="px-5 py-4">
                                    <Link
                                        class="underline-offset-4 hover:underline"
                                        :href="`/admin/reports/classes/${row.class_id}`"
                                        >{{ row.class }}</Link
                                    >
                                </td>
                                <td class="px-5 py-4">
                                    {{ row.eligible_students }}
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
                                <td class="px-5 py-4">
                                    <Badge
                                        variant="outline"
                                        class="capitalize"
                                        >{{ label(row.purpose) }}</Badge
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="p-8 text-center text-sm text-muted-foreground">
                    No active assessment assignments in this scope.
                </p></CardContent
            >
        </Card>
    </div>
</template>
