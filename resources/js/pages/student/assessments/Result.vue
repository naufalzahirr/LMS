<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Clock3, XCircle } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { AssessmentResult } from '@/types/assessment-attempt';

defineProps<{ result: AssessmentResult }>();

function displayAnswer(value: string | string[] | boolean | null): string {
    if (Array.isArray(value)) {
        return value.join(', ');
    }

    if (typeof value === 'boolean') {
        return value ? 'True' : 'False';
    }

    return value ?? 'No answer';
}
</script>

<template>
    <Head :title="`${result.assessment_title} result`" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="result.assessment_title"
                :description="`Attempt ${result.attempt_number}`"
            />
            <Button variant="outline" as-child
                ><Link :href="result.assessment_url"
                    ><ArrowLeft /> Assessment</Link
                ></Button
            >
        </div>

        <Card>
            <CardContent
                class="flex flex-col items-center gap-3 py-10 text-center"
            >
                <Clock3
                    v-if="result.status === 'pending_grading'"
                    class="size-12 text-amber-600"
                />
                <CheckCircle2 v-else class="size-12 text-emerald-600" />
                <h2 class="text-xl font-semibold">
                    {{
                        result.status === 'pending_grading'
                            ? 'Submitted for grading'
                            : 'Attempt graded'
                    }}
                </h2>
                <p
                    v-if="result.status === 'graded'"
                    class="text-3xl font-semibold"
                >
                    {{ result.earned_points }} / {{ result.max_points }}
                    <span class="text-lg text-muted-foreground"
                        >({{ result.percentage }}%)</span
                    >
                </p>
                <p v-else class="text-muted-foreground">
                    Your objective answers were saved. The final score will
                    appear after essay grading.
                </p>
                <p
                    v-if="
                        result.status === 'graded' && !result.detailed_feedback
                    "
                    class="text-sm text-muted-foreground"
                >
                    Detailed feedback is not available yet for this assignment.
                </p>
            </CardContent>
        </Card>

        <div
            v-if="result.detailed_feedback && result.questions?.length"
            class="space-y-4"
        >
            <Card
                v-for="(question, index) in result.questions"
                :key="question.id"
            >
                <CardHeader>
                    <div class="flex items-start justify-between gap-3">
                        <CardTitle class="text-base"
                            >{{ index + 1 }}. {{ question.prompt }}</CardTitle
                        >
                        <Badge variant="outline"
                            >{{ question.points_earned ?? 0 }} /
                            {{ question.question_points }}</Badge
                        >
                    </div>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <div class="flex items-center gap-2">
                        <CheckCircle2
                            v-if="question.correct === true"
                            class="size-5 text-emerald-600"
                        />
                        <XCircle
                            v-else-if="question.correct === false"
                            class="size-5 text-destructive"
                        />
                        <span class="font-medium">Your answer:</span>
                        {{ displayAnswer(question.student_answer) }}
                    </div>
                    <p v-if="question.correct_answer !== null">
                        <span class="font-medium">Correct answer:</span>
                        {{ displayAnswer(question.correct_answer) }}
                    </p>
                    <p
                        v-if="question.explanation"
                        class="rounded-lg bg-muted/50 p-3"
                    >
                        <span class="font-medium">Explanation:</span>
                        {{ question.explanation }}
                    </p>
                    <p v-if="question.feedback" class="rounded-lg border p-3">
                        <span class="font-medium">Tutor feedback:</span>
                        {{ question.feedback }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
