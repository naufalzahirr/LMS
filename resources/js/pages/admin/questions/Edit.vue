<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import QuestionController from '@/actions/App/Http/Controllers/Admin/QuestionController';
import QuestionFormFields from '@/components/admin/QuestionFormFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index } from '@/routes/admin/questions';
import type { AcademicStatusOption } from '@/types/academic';
import type {
    AuthoringOptions,
    EditableQuestion,
    QuestionType,
    SelectOption,
} from '@/types/assessment';

defineProps<
    AuthoringOptions & {
        question: EditableQuestion;
        questionTypes: SelectOption<QuestionType>[];
        statuses: AcademicStatusOption[];
    }
>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Questions', href: index() },
            { title: 'Edit', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Edit question" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Edit question"
            description="Changing type replaces the normalized answer key for the previous type."
        />
        <Card
            ><CardContent>
                <Form
                    v-bind="QuestionController.update.form(question.id)"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <QuestionFormFields
                        :programs="programs"
                        :courses="courses"
                        :competencies="competencies"
                        :question-banks="questionBanks"
                        :question-types="questionTypes"
                        :statuses="statuses"
                        :errors="errors"
                        :initial="question"
                    />
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child
                            ><Link :href="QuestionController.show(question.id)"
                                >Cancel</Link
                            ></Button
                        ><Button type="submit" :disabled="processing"
                            >Save changes</Button
                        >
                    </div>
                </Form>
            </CardContent></Card
        >
    </div>
</template>
