<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    Save,
    XCircle,
} from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { statusLabel } from '@/lib/assessmentAttempt';
import type { QuestionType } from '@/types/assessment';
import type { AssessmentAttemptStatus } from '@/types/assessment-attempt';

type Essay = {
    id: number;
    prompt: string;
    answer_text: string | null;
    points: string;
    manual_score: string | null;
    feedback: string | null;
};
type AutoGradedQuestion = {
    id: number;
    question_type: QuestionType;
    prompt: string;
    points: string;
    earned: string | null;
    is_correct: boolean | null;
    student_answer: string[] | string | boolean | null;
};
type GradingAttempt = {
    id: number;
    assessment_title: string;
    student: string;
    email: string;
    attempt_number: number;
    status: AssessmentAttemptStatus;
    submitted_at: string | null;
    auto_points: string | null;
    earned_points: string | null;
    max_points: string;
    percentage: string | null;
};

const props = defineProps<{
    attempt: GradingAttempt;
    essays: Essay[];
    auto_graded: AutoGradedQuestion[];
    previousUrl: string | null;
    nextUrl: string | null;
    submitUrl: string;
    backUrl: string;
}>();
const form = useForm({
    grades: props.essays.map((essay) => ({
        attempt_question_id: essay.id,
        manual_score: essay.manual_score ?? '',
        feedback: essay.feedback ?? '',
    })),
});
// Only essays a Tutor has actually scored are sent — partial grading
// progress is a legitimate save, not just a "finalize everything" action.
form.transform((data) => ({
    ...data,
    grades: data.grades.filter((grade) => grade.manual_score !== ''),
}));

const gradedCount = computed(
    () => props.essays.filter((essay) => essay.manual_score !== null).length,
);
const allFilled = computed(() =>
    form.grades.every((grade) => grade.manual_score !== ''),
);
const saveLabel = computed(() => {
    if (!allFilled.value) {
        return 'Save grading progress';
    }

    return props.attempt.status === 'graded'
        ? 'Save regrade'
        : 'Finalize grading';
});

function displayAnswer(answer: string[] | string | boolean | null): string {
    if (answer === null) {
        return 'No answer submitted.';
    }

    if (Array.isArray(answer)) {
        return answer.length ? answer.join(', ') : 'No answer submitted.';
    }

    if (typeof answer === 'boolean') {
        return answer ? 'True' : 'False';
    }

    return answer;
}

function submit(): void {
    form.patch(props.submitUrl, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Grade ${attempt.student}`" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <div class="min-w-0">
                <Heading
                    :title="attempt.assessment_title"
                    :description="`${attempt.student} · Attempt ${attempt.attempt_number}`"
                />
            </div>
            <div class="flex flex-wrap gap-2 lg:shrink-0">
                <Button variant="outline" :disabled="!previousUrl" as-child>
                    <Link v-if="previousUrl" :href="previousUrl"
                        ><ArrowLeft /> Previous</Link
                    >
                    <span v-else><ArrowLeft /> Previous</span>
                </Button>
                <Button variant="outline" :disabled="!nextUrl" as-child>
                    <Link v-if="nextUrl" :href="nextUrl"
                        >Next <ArrowRight
                    /></Link>
                    <span v-else>Next <ArrowRight /></span>
                </Button>
                <Button variant="outline" as-child
                    ><Link :href="backUrl"><ArrowLeft /> Attempts</Link></Button
                >
            </div>
        </div>
        <Card>
            <CardContent class="grid gap-4 sm:grid-cols-4">
                <div>
                    <p class="text-sm text-muted-foreground">Student</p>
                    <p class="font-medium">{{ attempt.student }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ attempt.email }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Status</p>
                    <Badge class="mt-1" variant="outline">{{
                        statusLabel(attempt.status)
                    }}</Badge>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Auto points</p>
                    <p class="font-medium">{{ attempt.auto_points ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Current total</p>
                    <p class="font-medium">
                        {{ attempt.earned_points ?? 'Pending' }} /
                        {{ attempt.max_points }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card v-if="auto_graded.length">
            <CardHeader>
                <CardTitle class="text-base">Auto-graded questions</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div
                    v-for="question in auto_graded"
                    :key="question.id"
                    class="flex items-start justify-between gap-3 rounded-lg border p-3"
                >
                    <div class="space-y-1">
                        <p class="text-sm font-medium">{{ question.prompt }}</p>
                        <p class="text-xs text-muted-foreground">
                            Answer: {{ displayAnswer(question.student_answer) }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Badge variant="outline"
                            >{{ question.points }} pts</Badge
                        >
                        <CheckCircle2
                            v-if="question.is_correct"
                            class="size-5 text-green-600 dark:text-green-500"
                        />
                        <XCircle v-else class="size-5 text-destructive" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <form class="space-y-4" @submit.prevent="submit">
            <div v-if="essays.length" class="text-sm text-muted-foreground">
                {{ gradedCount }} of {{ essays.length }} essays graded
            </div>
            <Card v-for="(essay, index) in essays" :key="essay.id">
                <CardHeader
                    ><div class="flex items-start justify-between gap-3">
                        <CardTitle class="text-base"
                            >{{ index + 1 }}. {{ essay.prompt }}</CardTitle
                        ><Badge variant="outline">{{ essay.points }} pts</Badge>
                    </div></CardHeader
                >
                <CardContent class="space-y-4">
                    <div
                        class="rounded-lg border bg-muted/30 p-4 whitespace-pre-line"
                    >
                        {{ essay.answer_text || 'No answer submitted.' }}
                    </div>
                    <input
                        v-model="form.grades[index].attempt_question_id"
                        type="hidden"
                    />
                    <div class="grid gap-4 md:grid-cols-[12rem_1fr]">
                        <div class="grid gap-2">
                            <Label :for="`score-${essay.id}`">Score</Label
                            ><Input
                                :id="`score-${essay.id}`"
                                v-model="form.grades[index].manual_score"
                                type="number"
                                min="0"
                                :max="essay.points"
                                step="0.01"
                            /><InputError
                                :message="
                                    form.errors[`grades.${index}.manual_score`]
                                "
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`feedback-${essay.id}`">Feedback</Label
                            ><Textarea
                                :id="`feedback-${essay.id}`"
                                v-model="form.grades[index].feedback"
                                rows="3"
                            /><InputError
                                :message="
                                    form.errors[`grades.${index}.feedback`]
                                "
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
            <div class="flex justify-end">
                <Button
                    type="submit"
                    size="lg"
                    :disabled="
                        form.processing ||
                        !form.grades.some((g) => g.manual_score !== '')
                    "
                    ><Save /> {{ saveLabel }}</Button
                >
            </div>
        </form>
    </div>
</template>
