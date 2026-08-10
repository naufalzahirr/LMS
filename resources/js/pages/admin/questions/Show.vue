<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, Pencil, X } from '@lucide/vue';
import QuestionController from '@/actions/App/Http/Controllers/Admin/QuestionController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/admin/questions';
import type { EditableQuestion } from '@/types/assessment';

type PreviewQuestion = EditableQuestion & {
    bank: string;
    competency: string;
    course: string;
    program: string;
};
defineProps<{ question: PreviewQuestion; canManage: boolean }>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Questions', href: index() },
            { title: 'Preview', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Question preview" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                title="Question preview"
                description="Review the question and its normalized answer key."
            />
            <div class="flex gap-2">
                <Button variant="outline" as-child
                    ><Link :href="index()"
                        ><ArrowLeft /> Questions</Link
                    ></Button
                ><Button v-if="canManage" as-child
                    ><Link :href="QuestionController.edit(question.id)"
                        ><Pencil /> Edit</Link
                    ></Button
                >
            </div>
        </div>
        <Card
            ><CardContent class="grid gap-5 pt-2 md:grid-cols-4"
                ><div>
                    <p class="text-xs text-muted-foreground">
                        Program / Course
                    </p>
                    <p class="mt-1 text-sm">
                        {{ question.program }} / {{ question.course }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Competency</p>
                    <p class="mt-1 text-sm">{{ question.competency }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Question bank</p>
                    <p class="mt-1 text-sm">{{ question.bank }}</p>
                </div>
                <div class="flex flex-wrap items-start gap-2">
                    <Badge variant="outline">{{ question.question_type }}</Badge
                    ><Badge
                        :variant="
                            question.status === 'active'
                                ? 'default'
                                : 'secondary'
                        "
                        >{{ question.status }}</Badge
                    ><Badge variant="secondary"
                        >{{ question.default_points }} pts</Badge
                    >
                </div></CardContent
            ></Card
        >
        <Card
            ><CardHeader><CardTitle>Prompt</CardTitle></CardHeader
            ><CardContent
                ><p class="leading-7 whitespace-pre-wrap">
                    {{ question.prompt }}
                </p></CardContent
            ></Card
        >
        <Card
            ><CardHeader><CardTitle>Answer key</CardTitle></CardHeader
            ><CardContent class="space-y-4">
                <div
                    v-if="
                        ['multiple_choice', 'multiple_select'].includes(
                            question.question_type,
                        )
                    "
                    class="divide-y rounded-lg border"
                >
                    <div
                        v-for="option in question.options"
                        :key="option.id"
                        class="flex items-center gap-3 p-3"
                    >
                        <Check
                            v-if="option.is_correct"
                            class="size-5 text-emerald-600"
                        /><X v-else class="size-5 text-muted-foreground" /><span
                            :class="option.is_correct ? 'font-medium' : ''"
                            >{{ option.option_text }}</span
                        >
                    </div>
                </div>
                <p
                    v-else-if="question.question_type === 'true_false'"
                    class="text-lg font-medium"
                >
                    {{ question.correct_boolean ? 'True' : 'False' }}
                </p>
                <div
                    v-else-if="question.question_type === 'short_answer'"
                    class="flex flex-wrap gap-2"
                >
                    <Badge
                        v-for="answer in question.accepted_answers"
                        :key="answer.id"
                        variant="outline"
                        >{{ answer.answer_text
                        }}<span
                            v-if="answer.case_sensitive"
                            class="ml-1 text-muted-foreground"
                            >(case-sensitive)</span
                        ></Badge
                    >
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Essay response — manually evaluated; no automatic answer
                    key.
                </p>
                <div v-if="question.explanation" class="border-t pt-4">
                    <p class="mb-1 text-sm font-medium">Explanation</p>
                    <p
                        class="text-sm whitespace-pre-wrap text-muted-foreground"
                    >
                        {{ question.explanation }}
                    </p>
                </div>
            </CardContent></Card
        >
    </div>
</template>
