<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Download,
    Target,
    UsersRound,
} from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import MasteryHeatmap from '@/components/mastery/MasteryHeatmap.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Report = {
    learning_class: {
        id: number;
        name: string;
        code: string;
        program: string;
        course: string;
        status: string;
    };
    summary: {
        students: number;
        competencies: number;
        mastery_rate: number;
        needs_remedial: number;
    };
    students: {
        enrollment_id: number;
        student: string;
        email: string;
        enrollment_status: string;
        completed_lessons: number;
        total_lessons: number;
        lesson_percentage: number;
        competencies_mastered: number;
        competencies_total: number;
        needs_remedial: number;
    }[];
    heatmap: InstanceType<typeof MasteryHeatmap>['$props']['heatmap'];
    assessments: {
        assessment: string;
        purpose: string;
        attempts: number;
        in_progress: number;
        pending_grading: number;
        graded: number;
        average_score: number | null;
    }[];
    attention: {
        student: string;
        email: string;
        competency: string;
        reasons: string[];
    }[];
};

defineProps<{
    report: Report;
    scope: 'admin' | 'tutor';
    backUrl: string;
    csvUrl: string | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Class progress report', href: '#' }] },
});

function label(value: string): string {
    return value.replaceAll('_', ' ');
}
</script>

<template>
    <Head :title="`${report.learning_class.name} progress`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="`${report.learning_class.name} progress`"
                :description="`${report.learning_class.program} / ${report.learning_class.course} · ${report.learning_class.code}`"
            />
            <div class="flex flex-wrap gap-2">
                <Button v-if="csvUrl" variant="outline" as-child>
                    <a :href="csvUrl"><Download /> Export CSV</a>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="backUrl"><ArrowLeft /> Back</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card
                ><CardContent class="flex items-center gap-4"
                    ><UsersRound class="size-8 text-muted-foreground" />
                    <div>
                        <p class="text-2xl font-semibold">
                            {{ report.summary.students }}
                        </p>
                        <p class="text-sm text-muted-foreground">Students</p>
                    </div></CardContent
                ></Card
            >
            <Card
                ><CardContent class="flex items-center gap-4"
                    ><Target class="size-8 text-muted-foreground" />
                    <div>
                        <p class="text-2xl font-semibold">
                            {{ report.summary.competencies }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Competencies
                        </p>
                    </div></CardContent
                ></Card
            >
            <Card
                ><CardContent
                    ><p class="text-2xl font-semibold">
                        {{ report.summary.mastery_rate }}%
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Mastery rate
                    </p></CardContent
                ></Card
            >
            <Card
                ><CardContent class="flex items-center gap-4"
                    ><AlertTriangle class="size-8 text-amber-600" />
                    <div>
                        <p class="text-2xl font-semibold">
                            {{ report.summary.needs_remedial }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Needs remedial
                        </p>
                    </div></CardContent
                ></Card
            >
        </div>

        <Card class="gap-0 overflow-hidden py-0">
            <CardHeader class="py-5"
                ><CardTitle>Student progress</CardTitle></CardHeader
            >
            <CardContent class="p-0">
                <div v-if="report.students.length" class="overflow-x-auto">
                    <table class="w-full min-w-[54rem] text-sm">
                        <thead class="border-y bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Student</th>
                                <th class="px-5 py-3">Enrollment</th>
                                <th class="px-5 py-3">Lessons</th>
                                <th class="px-5 py-3">Mastery</th>
                                <th class="px-5 py-3">Needs remedial</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="student in report.students"
                                :key="student.enrollment_id"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium">
                                        {{ student.student }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ student.email }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 capitalize">
                                    <Badge variant="outline">{{
                                        label(student.enrollment_status)
                                    }}</Badge>
                                </td>
                                <td class="px-5 py-4">
                                    {{ student.completed_lessons }} /
                                    {{ student.total_lessons }} ·
                                    {{ student.lesson_percentage }}%
                                </td>
                                <td class="px-5 py-4">
                                    {{ student.competencies_mastered }} /
                                    {{ student.competencies_total }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ student.needs_remedial }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="p-8 text-center text-sm text-muted-foreground">
                    No students are enrolled in this class.
                </p>
            </CardContent>
        </Card>

        <MasteryHeatmap :heatmap="report.heatmap" />

        <div class="grid gap-6 xl:grid-cols-2">
            <Card class="gap-0 overflow-hidden py-0">
                <CardHeader class="py-5"
                    ><CardTitle>Assessment summary</CardTitle></CardHeader
                >
                <CardContent class="p-0">
                    <div
                        v-if="report.assessments.length"
                        class="overflow-x-auto"
                    >
                        <table class="w-full min-w-[38rem] text-sm">
                            <thead class="border-y bg-muted/40 text-left">
                                <tr>
                                    <th class="px-5 py-3">Assessment</th>
                                    <th class="px-5 py-3">Attempts</th>
                                    <th class="px-5 py-3">Statuses</th>
                                    <th class="px-5 py-3">Avg. graded</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="assessment in report.assessments"
                                    :key="assessment.assessment"
                                >
                                    <td class="px-5 py-4">
                                        <p class="font-medium">
                                            {{ assessment.assessment }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground capitalize"
                                        >
                                            {{ label(assessment.purpose) }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4">
                                        {{ assessment.attempts }}
                                    </td>
                                    <td class="px-5 py-4 text-xs">
                                        {{ assessment.in_progress }} in progress
                                        ·
                                        {{ assessment.pending_grading }} pending
                                        · {{ assessment.graded }} graded
                                    </td>
                                    <td class="px-5 py-4">
                                        {{
                                            assessment.average_score === null
                                                ? '—'
                                                : `${assessment.average_score}%`
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p
                        v-else
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        No assessments are assigned.
                    </p>
                </CardContent>
            </Card>

            <Card class="gap-0 overflow-hidden py-0">
                <CardHeader class="py-5"
                    ><CardTitle>Needs attention</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Only remedial needs and exhausted mastery attempts
                        appear here.
                    </p></CardHeader
                >
                <CardContent class="p-0">
                    <div v-if="report.attention.length" class="divide-y">
                        <div
                            v-for="(item, index) in report.attention"
                            :key="`${item.email}-${item.competency}-${index}`"
                            class="px-5 py-4"
                        >
                            <p class="font-medium">
                                {{ item.student }} · {{ item.competency }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ item.email }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Badge
                                    v-for="reason in item.reasons"
                                    :key="reason"
                                    variant="secondary"
                                    >{{ reason }}</Badge
                                >
                            </div>
                        </div>
                    </div>
                    <p
                        v-else
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        No students currently meet the attention rules.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
