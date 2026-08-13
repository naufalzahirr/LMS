<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpenCheck,
    CheckCircle2,
    ClipboardCheck,
    RotateCcw,
} from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    assessmentPerformanceLabel,
    attemptScoreLabel,
    percentageLabel,
} from '@/lib/learningAnalytics';

type CompetencyCell = {
    id: number;
    name: string;
    status: string;
    class: string;
    course: string;
    latest_score: string | null;
    best_score: string | null;
    required_score: string | null;
    action_url: string;
};
type ClassInsight = {
    id: number;
    name: string;
    course: string;
    program: string;
    completed_lessons: number;
    total_lessons: number;
    lesson_percentage: number | null;
    competencies_mastered: number;
    competencies_total: number;
    needs_attention: number;
    assessment_submitted: number;
    assessment_eligible: number;
    assessment_pending_grading: number;
    class_url: string;
    continue_url: string;
};
type AssessmentInsight = {
    attempt_id: number;
    assessment: string;
    purpose: string;
    class: string;
    attempt_number: number;
    status: string;
    percentage: string | null;
    date: string;
    url: string;
};

defineProps<{
    insights: {
        summary: {
            completed_lessons: number;
            total_lessons: number;
            lesson_percentage: number | null;
            competencies_mastered: number;
            competencies_total: number;
            mastery_percentage: number | null;
            needs_attention: number;
            assessment_eligible: number;
            assessment_submitted: number;
            assessment_graded: number;
            assessment_pending_grading: number;
            assessment_average: number | null;
        };
        observations: string[];
        classes: ClassInsight[];
        competencies: {
            mastered: CompetencyCell[];
            learning: CompetencyCell[];
            needs_attention: CompetencyCell[];
        };
        current_focus: CompetencyCell[];
        recent_assessments: AssessmentInsight[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My progress', href: '/student/progress' }],
    },
});

function statusLabel(value: string): string {
    if (value === 'needs_remedial') {
        return 'Needs attention';
    }

    if (value === 'ready_for_assessment') {
        return 'Ready for assessment';
    }

    return value.replaceAll('_', ' ');
}
</script>

<template>
    <Head title="My Progress" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="My progress"
            description="See what you have completed, what you have mastered, and where to focus next."
        />

        <section aria-labelledby="student-progress-overview" class="space-y-4">
            <h2 id="student-progress-overview" class="sr-only">
                Progress overview
            </h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card
                    ><CardContent class="flex items-center gap-4"
                        ><BookOpenCheck class="size-8 text-muted-foreground" />
                        <div>
                            <p class="text-2xl font-semibold">
                                {{ insights.summary.completed_lessons }}/{{
                                    insights.summary.total_lessons
                                }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Accessible lessons completed ·
                                {{
                                    percentageLabel(
                                        insights.summary.lesson_percentage,
                                    )
                                }}
                            </p>
                        </div></CardContent
                    ></Card
                >
                <Card
                    ><CardContent class="flex items-center gap-4"
                        ><CheckCircle2 class="size-8 text-emerald-600" />
                        <div>
                            <p class="text-2xl font-semibold">
                                {{ insights.summary.competencies_mastered }}/{{
                                    insights.summary.competencies_total
                                }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Active competencies mastered ·
                                {{
                                    percentageLabel(
                                        insights.summary.mastery_percentage,
                                    )
                                }}
                            </p>
                        </div></CardContent
                    ></Card
                >
                <Card
                    ><CardContent class="flex items-center gap-4"
                        ><RotateCcw class="size-8 text-amber-600" />
                        <div>
                            <p class="text-2xl font-semibold">
                                {{ insights.summary.needs_attention }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Competencies needing remedial attention
                            </p>
                        </div></CardContent
                    ></Card
                >
                <Card
                    ><CardContent class="flex items-center gap-4"
                        ><ClipboardCheck class="size-8 text-muted-foreground" />
                        <div>
                            <p class="font-semibold">
                                {{
                                    assessmentPerformanceLabel(
                                        insights.summary.assessment_average,
                                        insights.summary.assessment_graded,
                                    )
                                }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ insights.summary.assessment_submitted }}/{{
                                    insights.summary.assessment_eligible
                                }}
                                assigned assessments submitted ·
                                {{
                                    insights.summary.assessment_pending_grading
                                }}
                                pending grading
                            </p>
                        </div></CardContent
                    ></Card
                >
            </div>
            <Card
                ><CardContent
                    ><ul class="grid gap-2 text-sm sm:grid-cols-3">
                        <li
                            v-for="observation in insights.observations"
                            :key="observation"
                            class="rounded-md bg-muted/50 p-3"
                        >
                            {{ observation }}
                        </li>
                    </ul></CardContent
                ></Card
            >
        </section>

        <section class="space-y-4" aria-labelledby="current-focus">
            <div>
                <h2 id="current-focus" class="text-lg font-semibold">
                    Where to focus next
                </h2>
                <p class="text-sm text-muted-foreground">
                    Based only on your current learning and remedial states.
                </p>
            </div>
            <div
                v-if="insights.current_focus.length"
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
            >
                <Card v-for="cell in insights.current_focus" :key="cell.id"
                    ><CardContent class="flex h-full flex-col gap-3"
                        ><div>
                            <Badge
                                :variant="
                                    cell.status === 'needs_remedial'
                                        ? 'destructive'
                                        : 'secondary'
                                "
                                class="capitalize"
                                >{{ statusLabel(cell.status) }}</Badge
                            >
                            <p class="mt-3 font-medium">{{ cell.name }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ cell.class }} · {{ cell.course }}
                            </p>
                        </div>
                        <Button class="mt-auto" variant="outline" as-child
                            ><Link :href="cell.action_url"
                                >{{
                                    cell.status === 'needs_remedial'
                                        ? 'Open remedial learning'
                                        : 'Continue learning'
                                }}
                                <ArrowRight /></Link></Button></CardContent
                ></Card>
            </div>
            <Card v-else
                ><CardContent
                    class="py-8 text-center text-sm text-muted-foreground"
                    >No current learning focus is available yet.</CardContent
                ></Card
            >
        </section>

        <section class="space-y-4" aria-labelledby="class-progress">
            <div>
                <h2 id="class-progress" class="text-lg font-semibold">
                    Class progress
                </h2>
                <p class="text-sm text-muted-foreground">
                    Lesson counts use only active, non-draft lessons you can
                    access.
                </p>
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <Card v-for="item in insights.classes" :key="item.id"
                    ><CardHeader
                        ><CardTitle>{{ item.name }}</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            {{ item.program }} / {{ item.course }}
                        </p></CardHeader
                    ><CardContent class="space-y-4"
                        ><dl class="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-muted-foreground">
                                    Lessons completed
                                </dt>
                                <dd class="font-medium">
                                    {{ item.completed_lessons }}/{{
                                        item.total_lessons
                                    }}
                                    ·
                                    {{
                                        percentageLabel(item.lesson_percentage)
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    Competencies mastered
                                </dt>
                                <dd class="font-medium">
                                    {{ item.competencies_mastered }}/{{
                                        item.competencies_total
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    Needs attention
                                </dt>
                                <dd class="font-medium">
                                    {{ item.needs_attention }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    Assessments
                                </dt>
                                <dd class="font-medium">
                                    {{ item.assessment_submitted }}/{{
                                        item.assessment_eligible
                                    }}
                                    submitted ·
                                    {{ item.assessment_pending_grading }}
                                    pending
                                </dd>
                            </div>
                        </dl>
                        <div class="flex flex-wrap gap-2">
                            <Button as-child
                                ><Link :href="item.continue_url"
                                    >Continue learning
                                    <ArrowRight /></Link></Button
                            ><Button variant="outline" as-child
                                ><Link :href="item.class_url"
                                    >Open class</Link
                                ></Button
                            >
                        </div></CardContent
                    ></Card
                >
            </div>
            <Card v-if="!insights.classes.length"
                ><CardContent
                    class="py-8 text-center text-sm text-muted-foreground"
                    >You are not currently enrolled in an active learning
                    class.</CardContent
                ></Card
            >
        </section>

        <section class="space-y-4" aria-labelledby="competency-progress">
            <div>
                <h2 id="competency-progress" class="text-lg font-semibold">
                    Competency progress
                </h2>
                <p class="text-sm text-muted-foreground">
                    A competency with no progress record starts in Learning; it
                    is still included in the total.
                </p>
            </div>
            <div class="grid gap-4 xl:grid-cols-3">
                <Card
                    ><CardHeader
                        ><CardTitle
                            >Mastered ·
                            {{
                                insights.competencies.mastered.length
                            }}</CardTitle
                        ></CardHeader
                    ><CardContent class="space-y-3"
                        ><div
                            v-for="cell in insights.competencies.mastered"
                            :key="cell.id"
                            class="rounded-lg border p-3"
                        >
                            <p class="font-medium">{{ cell.name }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ cell.course }} · Best
                                {{ cell.best_score ?? '—'
                                }}<span v-if="cell.best_score">%</span>
                            </p>
                        </div>
                        <p
                            v-if="!insights.competencies.mastered.length"
                            class="text-sm text-muted-foreground"
                        >
                            No competencies mastered yet.
                        </p></CardContent
                    ></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle
                            >Learning ·
                            {{
                                insights.competencies.learning.length
                            }}</CardTitle
                        ></CardHeader
                    ><CardContent class="space-y-3"
                        ><Link
                            v-for="cell in insights.competencies.learning"
                            :key="cell.id"
                            :href="cell.action_url"
                            class="block rounded-lg border p-3 transition-colors hover:bg-muted/40"
                            ><p class="font-medium">{{ cell.name }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ statusLabel(cell.status) }} ·
                                {{ cell.course }}
                            </p></Link
                        >
                        <p
                            v-if="!insights.competencies.learning.length"
                            class="text-sm text-muted-foreground"
                        >
                            No competencies currently in learning.
                        </p></CardContent
                    ></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle
                            >Needs attention ·
                            {{
                                insights.competencies.needs_attention.length
                            }}</CardTitle
                        ></CardHeader
                    ><CardContent class="space-y-3"
                        ><Link
                            v-for="cell in insights.competencies
                                .needs_attention"
                            :key="cell.id"
                            :href="cell.action_url"
                            class="block rounded-lg border border-amber-300 p-3 transition-colors hover:bg-amber-50 dark:border-amber-900 dark:hover:bg-amber-950/30"
                            ><p class="font-medium">{{ cell.name }}</p>
                            <p class="text-sm text-muted-foreground">
                                Remedial learning · {{ cell.course }}
                            </p></Link
                        >
                        <p
                            v-if="!insights.competencies.needs_attention.length"
                            class="text-sm text-muted-foreground"
                        >
                            No competencies currently need remedial attention.
                        </p></CardContent
                    ></Card
                >
            </div>
        </section>

        <Card class="gap-0 overflow-hidden py-0">
            <CardHeader class="py-5"
                ><CardTitle>Recent assessment activity</CardTitle>
                <p class="text-sm text-muted-foreground">
                    The latest attempt for each active assignment. Pending
                    grading is not shown as a zero score.
                </p></CardHeader
            >
            <CardContent class="p-0"
                ><div
                    v-if="insights.recent_assessments.length"
                    class="overflow-x-auto"
                >
                    <table class="w-full min-w-[46rem] text-sm">
                        <thead class="border-y bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Assessment</th>
                                <th class="px-5 py-3">Class</th>
                                <th class="px-5 py-3">Attempt</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Score</th>
                                <th class="px-5 py-3">Date</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="item in insights.recent_assessments"
                                :key="item.attempt_id"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium">
                                        {{ item.assessment }}
                                    </p>
                                    <p
                                        class="text-xs text-muted-foreground capitalize"
                                    >
                                        {{ statusLabel(item.purpose) }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">{{ item.class }}</td>
                                <td class="px-5 py-4">
                                    #{{ item.attempt_number }}
                                </td>
                                <td class="px-5 py-4 capitalize">
                                    {{ statusLabel(item.status) }}
                                </td>
                                <td class="px-5 py-4">
                                    {{
                                        attemptScoreLabel(
                                            item.status,
                                            item.percentage,
                                        )
                                    }}
                                </td>
                                <td class="px-5 py-4">{{ item.date }}</td>
                                <td class="px-5 py-4 text-right">
                                    <Button size="sm" variant="outline" as-child
                                        ><Link :href="item.url">{{
                                            item.status === 'in_progress'
                                                ? 'Continue'
                                                : 'View result'
                                        }}</Link></Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="p-8 text-center text-sm text-muted-foreground">
                    No assessment attempts yet.
                </p></CardContent
            >
        </Card>
    </div>
</template>
