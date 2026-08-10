<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Play, RotateCcw } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { StudentAssessmentIntro } from '@/types/assessment-attempt';

const props = defineProps<{
    learningClass: { id: number; name: string; assessments_url: string };
    assessment: StudentAssessmentIntro;
}>();

function start(): void {
    if (props.assessment.in_progress_url) {
        router.visit(props.assessment.in_progress_url);

        return;
    }

    router.post(props.assessment.start_url);
}
</script>

<template>
    <Head :title="assessment.title" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="assessment.title"
                :description="`${assessment.competency} · ${assessment.purpose}`"
            />
            <Button variant="outline" as-child>
                <Link :href="learningClass.assessments_url"
                    ><ArrowLeft /> Assessments</Link
                >
            </Button>
        </div>

        <Card>
            <CardHeader>
                <div class="flex items-center justify-between gap-3">
                    <CardTitle>Before you begin</CardTitle>
                    <Badge variant="secondary">{{
                        assessment.availability
                    }}</Badge>
                </div>
            </CardHeader>
            <CardContent class="space-y-5">
                <p v-if="assessment.description" class="text-muted-foreground">
                    {{ assessment.description }}
                </p>
                <div
                    v-if="assessment.instructions"
                    class="rounded-lg border bg-muted/30 p-4 whitespace-pre-line"
                >
                    {{ assessment.instructions }}
                </div>
                <div
                    v-if="assessment.mastery"
                    class="rounded-lg border bg-muted/30 p-4 text-sm"
                >
                    <p class="font-medium">Mastery assessment</p>
                    <p class="mt-1 text-muted-foreground">
                        Status:
                        {{ assessment.mastery.status.replaceAll('_', ' ') }} ·
                        Best {{ assessment.mastery.best_score ?? '—' }}% ·
                        Required {{ assessment.mastery.required_score }}%
                    </p>
                    <p
                        v-if="assessment.mastery.message"
                        class="mt-2 text-amber-700 dark:text-amber-300"
                    >
                        {{ assessment.mastery.message }}
                    </p>
                    <Button
                        v-if="assessment.mastery.remedial_url"
                        class="mt-3"
                        variant="outline"
                        as-child
                    >
                        <Link :href="assessment.mastery.remedial_url">
                            Continue remedial learning
                        </Link>
                    </Button>
                </div>
                <div class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-muted-foreground">Questions</p>
                        <p class="font-medium">
                            {{ assessment.question_count }}
                        </p>
                    </div>
                    <div>
                        <p class="text-muted-foreground">Total points</p>
                        <p class="font-medium">{{ assessment.total_points }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground">Attempts</p>
                        <p class="font-medium">
                            {{ assessment.attempts_used }} /
                            {{ assessment.max_attempts }}
                        </p>
                    </div>
                    <div>
                        <p class="text-muted-foreground">Window</p>
                        <p class="font-medium">
                            {{ assessment.opens_at ?? 'Open now' }} –
                            {{ assessment.closes_at ?? 'No deadline' }}
                        </p>
                    </div>
                </div>
                <Button :disabled="!assessment.can_start" @click="start">
                    <RotateCcw v-if="assessment.in_progress_url" />
                    <Play v-else />
                    {{ assessment.start_label }}
                </Button>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Attempt history</CardTitle></CardHeader>
            <CardContent>
                <div
                    v-if="assessment.attempts.length"
                    class="divide-y rounded-lg border"
                >
                    <div
                        v-for="attempt in assessment.attempts"
                        :key="attempt.attempt_number"
                        class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="font-medium">
                                Attempt {{ attempt.attempt_number }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    attempt.submitted_at ??
                                    `Started ${attempt.started_at}`
                                }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <Badge variant="outline">{{
                                attempt.status.replace('_', ' ')
                            }}</Badge>
                            <span
                                v-if="attempt.status === 'graded'"
                                class="font-medium"
                                >{{ attempt.earned_points }} /
                                {{ attempt.max_points }} ({{
                                    attempt.percentage
                                }}%)</span
                            >
                            <Button
                                v-if="attempt.result_url"
                                variant="outline"
                                size="sm"
                                as-child
                                ><Link :href="attempt.result_url"
                                    >Result</Link
                                ></Button
                            >
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No attempts yet.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
