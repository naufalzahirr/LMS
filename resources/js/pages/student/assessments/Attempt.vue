<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Send } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import AnswerStatusBadge from '@/components/assessment-attempt/AnswerStatusBadge.vue';
import QuestionNavigator from '@/components/assessment-attempt/QuestionNavigator.vue';
import SubmitConfirmDialog from '@/components/assessment-attempt/SubmitConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    countAnswered,
    isQuestionAnswered,
    unansweredQuestionIds,
} from '@/lib/assessmentAttempt';
import type { AnswerSaveState, LocalAnswer } from '@/lib/assessmentAttempt';
import { formatRelativeTime } from '@/lib/utils';
import type {
    AssessmentPlayer,
    AssessmentPlayerQuestion,
} from '@/types/assessment-attempt';

const DEBOUNCE_MS = 800;

const props = defineProps<{ attempt: AssessmentPlayer }>();

const answers = reactive<Record<number, LocalAnswer>>(
    Object.fromEntries(
        props.attempt.questions.map((question) => [
            question.id,
            {
                answer_text: question.answer.answer_text ?? '',
                answer_boolean: question.answer.answer_boolean,
                selected_option_ids: [...question.answer.selected_option_ids],
            },
        ]),
    ),
);
const answerStatus = reactive<Record<number, AnswerSaveState>>(
    Object.fromEntries(
        props.attempt.questions.map((question) => [question.id, 'idle']),
    ),
);
const debounceTimers = new Map<number, ReturnType<typeof setTimeout>>();
const activeSaveCount = ref(0);
const currentQuestionId = ref<number | null>(
    props.attempt.questions[0]?.id ?? null,
);
const showSubmitDialog = ref(false);
const isSubmitting = ref(false);

const answeredIds = computed(
    () =>
        new Set(
            props.attempt.questions
                .filter((question) =>
                    isQuestionAnswered(
                        question.question_type,
                        answers[question.id],
                    ),
                )
                .map((question) => question.id),
        ),
);
const answeredCount = computed(() =>
    countAnswered(props.attempt.questions, answers),
);
const totalQuestions = computed(() => props.attempt.questions.length);
const progressPercent = computed(() =>
    totalQuestions.value === 0
        ? 0
        : Math.round((answeredCount.value / totalQuestions.value) * 100),
);

function performSave(question: AssessmentPlayerQuestion): void {
    answerStatus[question.id] = 'saving';
    activeSaveCount.value++;
    router.patch(question.answer_url, answers[question.id], {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            answerStatus[question.id] = 'saved';
        },
        onError: () => {
            answerStatus[question.id] = 'error';
            toast.error(
                'Unable to save your answer. Check your connection and try again.',
            );
        },
        onFinish: () => {
            activeSaveCount.value--;
        },
    });
}

function scheduleSave(
    question: AssessmentPlayerQuestion,
    immediate = false,
): void {
    const existingTimer = debounceTimers.get(question.id);

    if (existingTimer) {
        clearTimeout(existingTimer);
        debounceTimers.delete(question.id);
    }

    if (immediate) {
        performSave(question);

        return;
    }

    debounceTimers.set(
        question.id,
        setTimeout(() => {
            debounceTimers.delete(question.id);
            performSave(question);
        }, DEBOUNCE_MS),
    );
}

function flushPendingSaves(): void {
    for (const [id, timer] of debounceTimers) {
        clearTimeout(timer);
        debounceTimers.delete(id);

        const question = props.attempt.questions.find(
            (candidate) => candidate.id === id,
        );

        if (question) {
            performSave(question);
        }
    }
}

function chooseSingle(
    question: AssessmentPlayerQuestion,
    optionId: number,
): void {
    answers[question.id].selected_option_ids = [optionId];
    scheduleSave(question, true);
}

function toggleMultiple(
    question: AssessmentPlayerQuestion,
    optionId: number,
): void {
    const selected = answers[question.id].selected_option_ids;
    answers[question.id].selected_option_ids = selected.includes(optionId)
        ? selected.filter((id) => id !== optionId)
        : [...selected, optionId];
    scheduleSave(question, true);
}

function chooseBoolean(
    question: AssessmentPlayerQuestion,
    value: boolean,
): void {
    answers[question.id].answer_boolean = value;
    scheduleSave(question, true);
}

function scrollToQuestion(id: number): void {
    currentQuestionId.value = id;
    document
        .getElementById(`question-${id}`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function handleFocusIn(event: FocusEvent): void {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    const container = target.closest<HTMLElement>('[data-question-id]');
    const id = container ? Number(container.dataset.questionId) : NaN;

    if (!Number.isNaN(id)) {
        currentQuestionId.value = id;
    }
}

function openSubmitDialog(): void {
    flushPendingSaves();
    showSubmitDialog.value = true;
}

function reviewUnanswered(): void {
    showSubmitDialog.value = false;
    const [firstUnanswered] = unansweredQuestionIds(
        props.attempt.questions,
        answers,
    );

    if (firstUnanswered !== undefined) {
        scrollToQuestion(firstUnanswered);
    }
}

function confirmSubmit(): void {
    isSubmitting.value = true;
    router.post(
        props.attempt.submit_url,
        {},
        {
            onError: () => {
                showSubmitDialog.value = false;
                toast.error(
                    'This attempt could not be submitted. It may already be submitted.',
                );
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}
</script>

<template>
    <Head
        :title="`${attempt.assessment_title} · Attempt ${attempt.attempt_number}`"
    />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="attempt.assessment_title"
                :description="`Attempt ${attempt.attempt_number} · Started ${attempt.started_at}`"
            />
            <Button variant="outline" as-child
                ><Link :href="attempt.back_url"
                    ><ArrowLeft /> Assessment</Link
                ></Button
            >
        </div>

        <div class="flex flex-col gap-3 rounded-xl border bg-card p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-medium">
                    Question {{ answeredCount }} of
                    {{ totalQuestions }} answered
                </p>
                <p
                    v-if="attempt.closes_at"
                    class="text-sm text-muted-foreground"
                >
                    Closes {{ formatRelativeTime(attempt.closes_at) }}
                </p>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full bg-primary transition-all"
                    :style="{ width: `${progressPercent}%` }"
                />
            </div>
            <QuestionNavigator
                :questions="attempt.questions"
                :answered-ids="answeredIds"
                :current-id="currentQuestionId"
                @navigate="scrollToQuestion"
            />
        </div>

        <div class="space-y-6" @focusin="handleFocusIn">
            <Card
                v-for="(question, index) in attempt.questions"
                :id="`question-${question.id}`"
                :key="question.id"
                :data-question-id="question.id"
            >
                <CardHeader>
                    <div class="flex items-start justify-between gap-3">
                        <CardTitle class="text-base"
                            >{{ index + 1 }}. {{ question.prompt }}</CardTitle
                        >
                        <Badge variant="outline"
                            >{{ question.points }} pts</Badge
                        >
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-if="question.question_type === 'multiple_choice'"
                        class="space-y-3"
                    >
                        <Label
                            v-for="option in question.options"
                            :key="option.id"
                            class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 font-normal"
                        >
                            <input
                                type="radio"
                                :name="`question-${question.id}`"
                                :checked="
                                    answers[
                                        question.id
                                    ].selected_option_ids.includes(option.id)
                                "
                                @change="chooseSingle(question, option.id)"
                            />
                            {{ option.option_text }}
                        </Label>
                    </div>
                    <div
                        v-else-if="question.question_type === 'multiple_select'"
                        class="space-y-3"
                    >
                        <Label
                            v-for="option in question.options"
                            :key="option.id"
                            class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 font-normal"
                        >
                            <input
                                type="checkbox"
                                :checked="
                                    answers[
                                        question.id
                                    ].selected_option_ids.includes(option.id)
                                "
                                @change="toggleMultiple(question, option.id)"
                            />
                            {{ option.option_text }}
                        </Label>
                    </div>
                    <div
                        v-else-if="question.question_type === 'true_false'"
                        class="flex gap-3"
                    >
                        <Button
                            type="button"
                            :variant="
                                answers[question.id].answer_boolean === true
                                    ? 'default'
                                    : 'outline'
                            "
                            @click="chooseBoolean(question, true)"
                            >True</Button
                        >
                        <Button
                            type="button"
                            :variant="
                                answers[question.id].answer_boolean === false
                                    ? 'default'
                                    : 'outline'
                            "
                            @click="chooseBoolean(question, false)"
                            >False</Button
                        >
                    </div>
                    <Textarea
                        v-else-if="question.question_type === 'essay'"
                        v-model="answers[question.id].answer_text"
                        rows="7"
                        placeholder="Write your answer"
                        @input="scheduleSave(question)"
                    />
                    <Input
                        v-else
                        v-model="answers[question.id].answer_text"
                        placeholder="Enter your answer"
                        @input="scheduleSave(question)"
                    />
                    <AnswerStatusBadge :state="answerStatus[question.id]" />
                </CardContent>
            </Card>
        </div>

        <div class="flex justify-end rounded-xl border bg-card p-4">
            <Button size="lg" :disabled="isSubmitting" @click="openSubmitDialog"
                ><Send /> Submit attempt</Button
            >
        </div>

        <SubmitConfirmDialog
            v-model:open="showSubmitDialog"
            :unanswered-count="totalQuestions - answeredCount"
            :submitting="isSubmitting || activeSaveCount > 0"
            @review-unanswered="reviewUnanswered"
            @confirm="confirmSubmit"
        />
    </div>
</template>
