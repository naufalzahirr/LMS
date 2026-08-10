<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import QuestionController from '@/actions/App/Http/Controllers/Admin/QuestionController';
import QuestionFormFields from '@/components/admin/QuestionFormFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, index } from '@/routes/admin/questions';
import type { AcademicStatusOption } from '@/types/academic';
import type {
    AuthoringOptions,
    QuestionType,
    SelectOption,
} from '@/types/assessment';

defineProps<
    AuthoringOptions & {
        questionTypes: SelectOption<QuestionType>[];
        statuses: AcademicStatusOption[];
    }
>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Questions', href: index() },
            { title: 'Create', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Create question" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Create question"
            description="Create a reusable, competency-aligned question and answer key."
        />
        <Card
            ><CardContent>
                <Form
                    v-bind="QuestionController.store.form()"
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
                    />
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child
                            ><Link :href="index()">Cancel</Link></Button
                        ><Button type="submit" :disabled="processing"
                            >Create question</Button
                        >
                    </div>
                </Form>
            </CardContent></Card
        >
    </div>
</template>
