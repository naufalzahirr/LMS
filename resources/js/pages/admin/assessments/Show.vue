<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Archive,
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Pencil,
    Send,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import AssessmentController from '@/actions/App/Http/Controllers/Admin/AssessmentController';
import AssessmentQuestionController from '@/actions/App/Http/Controllers/Admin/AssessmentQuestionController';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/admin/assessments';
import type { AcademicStatus } from '@/types/academic';
import type {
    AssessmentPurpose,
    AssessmentStatus,
    QuestionType,
} from '@/types/assessment';

type AssessmentDetails = {
    id: number;
    competency_id: number;
    title: string;
    code: string | null;
    description: string | null;
    purpose: AssessmentPurpose;
    status: AssessmentStatus;
    instructions: string | null;
    shuffle_questions: boolean;
    competency: string;
    course: string;
    program: string;
};
type CompositionItem = {
    id: number;
    question_id: number;
    prompt: string;
    question_type: QuestionType;
    bank: string;
    points: string;
    status: AcademicStatus;
};
type QuestionCandidate = {
    id: number;
    label: string;
    type: QuestionType;
    bank: string;
    default_points: string;
};
const props = defineProps<{
    assessment: AssessmentDetails;
    questions: CompositionItem[];
    questionOptions: QuestionCandidate[];
    canManage: boolean;
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Assessments', href: index() },
            { title: 'Composition', href: index() },
        ],
    },
});
const page = usePage();
const actionErrors = computed<string[]>(() => {
    const errors: string[] = [];

    for (const key of ['assessment', 'question_id', 'points']) {
        const error = page.props.errors?.[key];

        if (typeof error === 'string') {
            errors.push(error);
        }
    }

    return errors;
});
const totalPoints = computed(() =>
    props.questions
        .reduce((total, item) => total + Number(item.points), 0)
        .toFixed(2),
);
function publish(): void {
    if (window.confirm('Publish this assessment?')) {
        router.patch(
            AssessmentController.publish.url(props.assessment.id),
            {},
            { preserveScroll: true },
        );
    }
}
function archive(): void {
    if (window.confirm('Archive this assessment? It will become read-only.')) {
        router.patch(
            AssessmentController.archive.url(props.assessment.id),
            {},
            { preserveScroll: true },
        );
    }
}
function move(item: CompositionItem, direction: 'up' | 'down'): void {
    router.patch(
        AssessmentQuestionController.move.url([
            props.assessment.id,
            item.id,
            direction,
        ]),
        {},
        { preserveScroll: true },
    );
}
function remove(item: CompositionItem): void {
    if (window.confirm('Remove this question from the assessment?')) {
        router.delete(
            AssessmentQuestionController.destroy.url([
                props.assessment.id,
                item.id,
            ]),
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Head :title="assessment.title" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <Heading
                :title="assessment.title"
                :description="`${assessment.program} / ${assessment.course} / ${assessment.competency}`"
            />
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child
                    ><Link :href="index()"
                        ><ArrowLeft /> Assessments</Link
                    ></Button
                ><Button v-if="canManage" variant="outline" as-child
                    ><Link :href="AssessmentController.edit(assessment.id)"
                        ><Pencil /> Edit</Link
                    ></Button
                ><Button
                    v-if="canManage && assessment.status === 'draft'"
                    @click="publish"
                    ><Send /> Publish</Button
                ><Button
                    v-if="canManage && assessment.status !== 'archived'"
                    variant="secondary"
                    @click="archive"
                    ><Archive /> Archive</Button
                >
            </div>
        </div>
        <AlertError
            v-if="actionErrors.length"
            title="Assessment action failed."
            :errors="actionErrors"
        />
        <Card
            ><CardContent class="grid gap-5 pt-2 sm:grid-cols-2 lg:grid-cols-5"
                ><div>
                    <p class="text-xs text-muted-foreground">Code</p>
                    <p class="mt-1">{{ assessment.code ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Purpose</p>
                    <Badge class="mt-1" variant="outline">{{
                        assessment.purpose
                    }}</Badge>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Status</p>
                    <Badge
                        class="mt-1"
                        :variant="
                            assessment.status === 'published'
                                ? 'default'
                                : 'secondary'
                        "
                        >{{ assessment.status }}</Badge
                    >
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Questions</p>
                    <p class="mt-1">{{ questions.length }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Total points</p>
                    <p class="mt-1">{{ totalPoints }}</p>
                </div>
                <p
                    v-if="assessment.description"
                    class="text-sm text-muted-foreground sm:col-span-2 lg:col-span-5"
                >
                    {{ assessment.description }}
                </p></CardContent
            ></Card
        >
        <Card v-if="assessment.instructions"
            ><CardHeader><CardTitle>Instructions</CardTitle></CardHeader
            ><CardContent
                ><p class="text-sm leading-6 whitespace-pre-wrap">
                    {{ assessment.instructions }}
                </p>
                <p class="mt-3 text-xs text-muted-foreground">
                    Question order:
                    {{ assessment.shuffle_questions ? 'shuffled' : 'fixed' }}
                </p></CardContent
            ></Card
        >
        <Card v-if="canManage"
            ><CardHeader><CardTitle>Add a question</CardTitle></CardHeader
            ><CardContent
                ><Form
                    v-bind="
                        AssessmentQuestionController.store.form(assessment.id)
                    "
                    class="grid items-end gap-3 md:grid-cols-[minmax(0,1fr)_10rem_auto]"
                    v-slot="{ errors, processing }"
                    ><div class="grid gap-2">
                        <Label for="question_id"
                            >Active question from
                            {{ assessment.competency }}</Label
                        ><Select name="question_id" required
                            ><SelectTrigger id="question_id" class="w-full"
                                ><SelectValue
                                    placeholder="Select a question" /></SelectTrigger
                            ><SelectContent
                                ><SelectItem
                                    v-for="question in questionOptions"
                                    :key="question.id"
                                    :value="question.id.toString()"
                                    ><span class="line-clamp-1"
                                        >{{ question.bank }} ·
                                        {{ question.label }}</span
                                    ></SelectItem
                                ></SelectContent
                            ></Select
                        ><InputError :message="errors.question_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="points">Points override</Label
                        ><Input
                            id="points"
                            name="points"
                            type="number"
                            min="0.01"
                            step="0.01"
                            placeholder="Use default"
                        /><InputError :message="errors.points" />
                    </div>
                    <Button
                        type="submit"
                        :disabled="processing || !questionOptions.length"
                        >Add question</Button
                    ></Form
                ></CardContent
            ></Card
        >
        <Card class="gap-0 overflow-hidden py-0"
            ><CardHeader class="py-5"
                ><CardTitle>Question composition</CardTitle></CardHeader
            ><CardContent class="p-0"
                ><div v-if="questions.length" class="divide-y">
                    <div
                        v-for="(item, index) in questions"
                        :key="item.id"
                        class="grid gap-4 p-5 lg:grid-cols-[2.5rem_minmax(0,1fr)_8rem_auto] lg:items-center"
                    >
                        <div class="font-mono text-sm text-muted-foreground">
                            {{ index + 1 }}
                        </div>
                        <div>
                            <p class="font-medium">{{ item.prompt }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Badge variant="outline">{{
                                    item.question_type
                                }}</Badge
                                ><span class="text-xs text-muted-foreground">{{
                                    item.bank
                                }}</span>
                            </div>
                        </div>
                        <Form
                            v-if="canManage"
                            v-bind="
                                AssessmentQuestionController.update.form([
                                    assessment.id,
                                    item.id,
                                ])
                            "
                            class="flex items-center gap-2"
                            v-slot="{ processing }"
                            ><Input
                                name="points"
                                type="number"
                                min="0.01"
                                step="0.01"
                                :default-value="item.points"
                                class="w-24"
                                aria-label="Question points"
                            /><Button
                                size="sm"
                                variant="outline"
                                type="submit"
                                :disabled="processing"
                                >Save</Button
                            ></Form
                        >
                        <p v-else class="text-sm">{{ item.points }} pts</p>
                        <div v-if="canManage" class="flex justify-end gap-1">
                            <Button
                                size="icon-sm"
                                variant="ghost"
                                :disabled="index === 0"
                                aria-label="Move up"
                                @click="move(item, 'up')"
                                ><ArrowUp /></Button
                            ><Button
                                size="icon-sm"
                                variant="ghost"
                                :disabled="index === questions.length - 1"
                                aria-label="Move down"
                                @click="move(item, 'down')"
                                ><ArrowDown /></Button
                            ><Button
                                size="icon-sm"
                                variant="destructive"
                                aria-label="Remove question"
                                @click="remove(item)"
                                ><Trash2
                            /></Button>
                        </div>
                    </div>
                </div>
                <p
                    v-else
                    class="p-10 text-center text-sm text-muted-foreground"
                >
                    No questions attached yet. Add at least one active question
                    before publishing.
                </p></CardContent
            ></Card
        >
    </div>
</template>
