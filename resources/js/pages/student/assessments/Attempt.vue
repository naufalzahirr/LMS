<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Check, Send } from '@lucide/vue';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type {
    AssessmentPlayer,
    AssessmentPlayerQuestion,
} from '@/types/assessment-attempt';

type LocalAnswer = {
    answer_text: string;
    answer_boolean: boolean | null;
    selected_option_ids: number[];
};

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

function chooseSingle(
    question: AssessmentPlayerQuestion,
    optionId: number,
): void {
    answers[question.id].selected_option_ids = [optionId];
}

function toggleMultiple(
    question: AssessmentPlayerQuestion,
    optionId: number,
): void {
    const selected = answers[question.id].selected_option_ids;
    answers[question.id].selected_option_ids = selected.includes(optionId)
        ? selected.filter((id) => id !== optionId)
        : [...selected, optionId];
}

function save(question: AssessmentPlayerQuestion): void {
    router.patch(question.answer_url, answers[question.id], {
        preserveScroll: true,
        preserveState: true,
    });
}

function submit(): void {
    if (
        window.confirm(
            'Submit this attempt? Answers cannot be changed afterward.',
        )
    ) {
        router.post(props.attempt.submit_url);
    }
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

        <Card v-for="(question, index) in attempt.questions" :key="question.id">
            <CardHeader>
                <div class="flex items-start justify-between gap-3">
                    <CardTitle class="text-base"
                        >{{ index + 1 }}. {{ question.prompt }}</CardTitle
                    >
                    <Badge variant="outline">{{ question.points }} pts</Badge>
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
                        @click="answers[question.id].answer_boolean = true"
                        >True</Button
                    >
                    <Button
                        type="button"
                        :variant="
                            answers[question.id].answer_boolean === false
                                ? 'default'
                                : 'outline'
                        "
                        @click="answers[question.id].answer_boolean = false"
                        >False</Button
                    >
                </div>
                <Textarea
                    v-else-if="question.question_type === 'essay'"
                    v-model="answers[question.id].answer_text"
                    rows="7"
                    placeholder="Write your answer"
                />
                <Input
                    v-else
                    v-model="answers[question.id].answer_text"
                    placeholder="Enter your answer"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="save(question)"
                    ><Check /> Save answer</Button
                >
            </CardContent>
        </Card>

        <div class="flex justify-end rounded-xl border bg-card p-4">
            <Button size="lg" @click="submit"><Send /> Submit attempt</Button>
        </div>
    </div>
</template>
