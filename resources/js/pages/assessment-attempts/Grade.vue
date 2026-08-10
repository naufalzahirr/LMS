<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { AssessmentAttemptStatus } from '@/types/assessment-attempt';

type Essay = {
    id: number;
    prompt: string;
    answer_text: string | null;
    points: string;
    manual_score: string | null;
    feedback: string | null;
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

function submit(): void {
    form.patch(props.submitUrl, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Grade ${attempt.student}`" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="attempt.assessment_title"
                :description="`${attempt.student} · Attempt ${attempt.attempt_number}`"
            />
            <Button variant="outline" as-child
                ><Link :href="backUrl"><ArrowLeft /> Attempts</Link></Button
            >
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
                        attempt.status.replace('_', ' ')
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
        <form class="space-y-4" @submit.prevent="submit">
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
                                required
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
                <Button type="submit" size="lg" :disabled="form.processing"
                    ><Save />
                    {{
                        attempt.status === 'graded'
                            ? 'Save regrade'
                            : 'Finalize grade'
                    }}</Button
                >
            </div>
        </form>
    </div>
</template>
