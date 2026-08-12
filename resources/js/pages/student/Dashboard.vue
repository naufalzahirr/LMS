<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpenCheck,
    CheckCircle2,
    ClipboardCheck,
    PartyPopper,
    RotateCcw,
    Trophy,
} from '@lucide/vue';
import EmptyState from '@/components/dashboard/EmptyState.vue';
import ProgressBar from '@/components/dashboard/ProgressBar.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard as dashboardRoute } from '@/routes';
import { index as studentClassesIndex } from '@/routes/student/classes';
import type { StudentDashboard } from '@/types/dashboard';

const props = defineProps<{ dashboard: StudentDashboard }>();

// Starting an assessment is a POST (it may create a new attempt), so it must
// not be triggered via a plain GET `<Link>` — that would 405 against the
// start route. Resuming/viewing an existing attempt is a normal GET visit.
function performAssessmentAction(action: {
    url: string | null;
    method: 'get' | 'post' | null;
}): void {
    if (!action.url) {
        return;
    }

    if (action.method === 'post') {
        router.post(action.url);
    } else {
        router.visit(action.url);
    }
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboardRoute() }],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="My learning"
            description="Continue where you left off and see what needs your attention."
        />

        <section aria-labelledby="continue-learning-heading" class="space-y-4">
            <h2 id="continue-learning-heading" class="text-lg font-semibold">
                Continue learning
            </h2>

            <div
                v-if="props.dashboard.continue_learning.length"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <Card
                    v-for="item in props.dashboard.continue_learning"
                    :key="item.enrollment_id"
                    class="flex flex-col border-primary/30"
                >
                    <CardHeader>
                        <CardTitle class="break-words">{{
                            item.name
                        }}</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            {{ item.program }} · {{ item.course }}
                        </p>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col gap-4">
                        <ProgressBar
                            :percentage="item.percentage"
                            label="Lesson progress"
                        />
                        <p class="text-sm text-muted-foreground">
                            {{ item.completed_lessons }} /
                            {{ item.total_lessons }} lessons complete
                        </p>
                        <p
                            v-if="item.continue_lesson_title"
                            class="text-sm break-words"
                        >
                            <span class="text-muted-foreground">Next up:</span>
                            {{ item.continue_lesson_title }}
                        </p>
                        <Button as-child class="mt-auto">
                            <Link :href="item.continue_url">
                                Continue learning <ArrowRight />
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <Card v-else>
                <CardContent>
                    <EmptyState
                        :icon="
                            props.dashboard.has_any_enrollment_history
                                ? PartyPopper
                                : BookOpenCheck
                        "
                        :title="
                            props.dashboard.has_any_enrollment_history
                                ? 'Your active classes are complete'
                                : 'You are not enrolled in any classes yet'
                        "
                        :description="
                            props.dashboard.has_any_enrollment_history
                                ? 'Check your learning history for past classes.'
                                : 'Ask an administrator to enroll you in a class to get started.'
                        "
                        :cta-label="
                            props.dashboard.has_any_enrollment_history
                                ? 'View my classes'
                                : undefined
                        "
                        :cta-href="
                            props.dashboard.has_any_enrollment_history
                                ? studentClassesIndex().url
                                : undefined
                        "
                    />
                </CardContent>
            </Card>
        </section>

        <section aria-labelledby="needs-attention-heading" class="space-y-4">
            <h2 id="needs-attention-heading" class="text-lg font-semibold">
                Needs your attention
            </h2>

            <Card
                v-if="
                    !props.dashboard.needs_attention.remedial.length &&
                    !props.dashboard.needs_attention.assessments_available.count
                "
            >
                <CardContent>
                    <EmptyState
                        :icon="CheckCircle2"
                        title="You're all caught up."
                        description="Nothing needs your attention right now."
                    />
                </CardContent>
            </Card>

            <div v-else class="grid gap-3">
                <Card
                    v-for="item in props.dashboard.needs_attention.remedial"
                    :key="`remedial-${item.enrollment_id}-${item.competency_name}`"
                >
                    <CardContent
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p class="font-medium">
                                Remedial learning: {{ item.competency_name }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ item.class_name }}
                            </p>
                        </div>
                        <Button variant="outline" as-child>
                            <Link :href="item.remedial_url">
                                <RotateCcw /> Continue remedial
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card
                    v-for="item in props.dashboard.needs_attention
                        .assessments_available.items"
                    :key="`assessment-${item.title}-${item.class_name}`"
                >
                    <CardContent
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p class="font-medium">{{ item.title }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ item.class_name }} · Available now
                            </p>
                        </div>
                        <Button
                            @click="
                                performAssessmentAction({
                                    url: item.start_url,
                                    method: item.method,
                                })
                            "
                        >
                            <ClipboardCheck /> Start assessment
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </section>

        <section aria-labelledby="progress-heading" class="space-y-4">
            <h2 id="progress-heading" class="text-lg font-semibold">
                Progress summary
            </h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <Card>
                    <CardContent class="flex items-center gap-4">
                        <BookOpenCheck class="size-8 text-muted-foreground" />
                        <div>
                            <p class="text-2xl font-semibold">
                                {{ props.dashboard.progress.completed_lessons }}
                                / {{ props.dashboard.progress.total_lessons }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Lessons completed
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4">
                        <Trophy class="size-8 text-emerald-600" />
                        <div>
                            <p class="text-2xl font-semibold">
                                {{
                                    props.dashboard.progress
                                        .competencies_mastered
                                }}
                                /
                                {{
                                    props.dashboard.progress.competencies_total
                                }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Competencies mastered
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </section>

        <section aria-labelledby="assessments-heading" class="space-y-4">
            <h2 id="assessments-heading" class="text-lg font-semibold">
                Assessments
            </h2>

            <Card v-if="!props.dashboard.assessments.length">
                <CardContent>
                    <EmptyState
                        :icon="ClipboardCheck"
                        title="No assessments need action right now."
                    />
                </CardContent>
            </Card>

            <div v-else class="grid gap-3">
                <Card
                    v-for="assessment in props.dashboard.assessments"
                    :key="assessment.id"
                >
                    <CardContent
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p class="font-medium">{{ assessment.title }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ assessment.class_name }} ·
                                {{ assessment.competency }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <Badge variant="outline">{{
                                assessment.availability
                            }}</Badge>
                            <Button
                                v-if="assessment.action.url"
                                size="sm"
                                @click="
                                    performAssessmentAction(assessment.action)
                                "
                            >
                                {{ assessment.action.label }}
                            </Button>
                            <span
                                v-else-if="assessment.action.label"
                                class="text-sm text-muted-foreground"
                            >
                                {{ assessment.action.label }}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </section>
    </div>
</template>
