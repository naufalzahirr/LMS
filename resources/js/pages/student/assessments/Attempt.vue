<script setup lang="ts">
import { Head, Link, router, useHttp } from '@inertiajs/vue3';
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
    createAnswerAutosaveCoordinator,
    hasUnsavedOrFailedAnswers,
} from '@/lib/answerAutosave';
import type { AnswerSnapshot, AutosaveState } from '@/lib/answerAutosave';
import {
    countAnswered,
    isQuestionAnswered,
    unansweredQuestionIds,
} from '@/lib/assessmentAttempt';
import type { LocalAnswer } from '@/lib/assessmentAttempt';
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
const answerStatus = reactive<Record<number, AutosaveState>>(
    Object.fromEntries(
        props.attempt.questions.map((question) => [question.id, 'idle']),
    ),
);

function snapshotOf(question: AssessmentPlayerQuestion): AnswerSnapshot {
    const current = answers[question.id];

    return {
        answer_text: current.answer_text,
        answer_boolean: current.answer_boolean,
        selected_option_ids: [...current.selected_option_ids],
    };
}

// One standalone HTTP transport per question. useHttp() never triggers an
// Inertia page visit or page-prop reload — a plain JSON request/response,
// with each question's in-flight state fully independent of the others.
const httpByQuestion = new Map(
    props.attempt.questions.map((question) => [
        question.id,
        useHttp({
            answer_text: '',
            answer_boolean: null as boolean | null,
            selected_option_ids: [] as number[],
        }),
    ]),
);

function performSave(
    question: AssessmentPlayerQuestion,
    snapshot: AnswerSnapshot,
): Promise<void> {
    const http = httpByQuestion.get(question.id);

    if (!http) {
        return Promise.reject(new Error('No transport for this question'));
    }

    http.answer_text = snapshot.answer_text;
    http.answer_boolean = snapshot.answer_boolean;
    http.selected_option_ids = snapshot.selected_option_ids;

    return new Promise((resolve, reject) => {
        http.patch(question.answer_url, {
            onSuccess: () => resolve(),
            onError: () => reject(new Error('The answer failed validation.')),
        }).catch(() => reject(new Error('The save request failed.')));
    });
}

const coordinators = new Map(
    props.attempt.questions.map((question) => [
        question.id,
        createAnswerAutosaveCoordinator(
            snapshotOf(question),
            (snapshot) => performSave(question, snapshot),
            (state) => {
                answerStatus[question.id] = state;
            },
        ),
    ]),
);
const debounceTimers = new Map<number, ReturnType<typeof setTimeout>>();
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
const hasSaveErrors = computed(() =>
    Object.values(answerStatus).some((state) => state === 'error'),
);
const unsavedOrFailed = computed(() => hasUnsavedOrFailedAnswers(answerStatus));

function recordChange(question: AssessmentPlayerQuestion): void {
    coordinators.get(question.id)?.update(snapshotOf(question));
}

function saveNow(question: AssessmentPlayerQuestion): void {
    const existingTimer = debounceTimers.get(question.id);

    if (existingTimer) {
        clearTimeout(existingTimer);
        debounceTimers.delete(question.id);
    }

    coordinators.get(question.id)?.flush();
}

function scheduleDebouncedSave(question: AssessmentPlayerQuestion): void {
    const existingTimer = debounceTimers.get(question.id);

    if (existingTimer) {
        clearTimeout(existingTimer);
    }

    debounceTimers.set(
        question.id,
        setTimeout(() => {
            debounceTimers.delete(question.id);
            coordinators.get(question.id)?.flush();
        }, DEBOUNCE_MS),
    );
}

function chooseSingle(
    question: AssessmentPlayerQuestion,
    optionId: number,
): void {
    answers[question.id].selected_option_ids = [optionId];
    recordChange(question);
    saveNow(question);
}

function toggleMultiple(
    question: AssessmentPlayerQuestion,
    optionId: number,
): void {
    const selected = answers[question.id].selected_option_ids;
    answers[question.id].selected_option_ids = selected.includes(optionId)
        ? selected.filter((id) => id !== optionId)
        : [...selected, optionId];
    recordChange(question);
    saveNow(question);
}

function chooseBoolean(
    question: AssessmentPlayerQuestion,
    value: boolean,
): void {
    answers[question.id].answer_boolean = value;
    recordChange(question);
    saveNow(question);
}

function onTextModelUpdate(
    question: AssessmentPlayerQuestion,
    value: string | number,
): void {
    // Input/Textarea use a passive v-model proxy, so their native input event
    // can bubble before the parent model receives the new value. Assign the
    // emitted value first so the autosave snapshot is always the exact text
    // currently visible to the Student.
    answers[question.id].answer_text = String(value);
    recordChange(question);
    scheduleDebouncedSave(question);
}

function retrySave(question: AssessmentPlayerQuestion): void {
    coordinators.get(question.id)?.retry();
}

function flushAllPendingSaves(): void {
    for (const [id, timer] of debounceTimers) {
        clearTimeout(timer);
        debounceTimers.delete(id);
    }

    for (const coordinator of coordinators.values()) {
        coordinator.flush();
    }
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
    // Every pending debounce is fired immediately so the dialog reflects true
    // save state as soon as it opens — the confirm action itself additionally
    // stays disabled the whole time any answer is not yet confirmed saved.
    flushAllPendingSaves();
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
    // The dialog's confirm button is disabled while unsavedOrFailed is true,
    // so by the time this runs every autosave has already been confirmed
    // persisted — there is no save left that could arrive after this request.
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
                        :model-value="answers[question.id].answer_text"
                        rows="7"
                        placeholder="Write your answer"
                        @update:model-value="
                            onTextModelUpdate(question, $event)
                        "
                        @blur="saveNow(question)"
                    />
                    <Input
                        v-else
                        :model-value="answers[question.id].answer_text"
                        placeholder="Enter your answer"
                        @update:model-value="
                            onTextModelUpdate(question, $event)
                        "
                        @blur="saveNow(question)"
                    />
                    <AnswerStatusBadge
                        :state="answerStatus[question.id]"
                        @retry="retrySave(question)"
                    />
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
            :has-unsaved-or-failed-answers="unsavedOrFailed"
            :has-save-errors="hasSaveErrors"
            :submitting="isSubmitting"
            @review-unanswered="reviewUnanswered"
            @confirm="confirmSubmit"
        />
    </div>
</template>
