<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import QuestionBankController from '@/actions/App/Http/Controllers/Admin/QuestionBankController';
import QuestionBankFormFields from '@/components/admin/QuestionBankFormFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index } from '@/routes/admin/question-banks';
import type { AcademicStatus, AcademicStatusOption } from '@/types/academic';
import type { AuthoringOptions } from '@/types/assessment';

type Bank = {
    id: number;
    course_id: number;
    name: string;
    code: string | null;
    description: string | null;
    status: AcademicStatus;
};
defineProps<
    AuthoringOptions & { questionBank: Bank; statuses: AcademicStatusOption[] }
>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Question banks', href: index() },
            { title: 'Edit', href: index() },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${questionBank.name}`" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="`Edit ${questionBank.name}`"
            description="Update the bank metadata and course scope."
        />
        <Card class="max-w-3xl"
            ><CardContent>
                <Form
                    v-bind="QuestionBankController.update.form(questionBank.id)"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <QuestionBankFormFields
                        :programs="programs"
                        :courses="courses"
                        :competencies="competencies"
                        :question-banks="questionBanks"
                        :statuses="statuses"
                        :errors="errors"
                        :initial="questionBank"
                    />
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child
                            ><Link :href="index()">Cancel</Link></Button
                        ><Button type="submit" :disabled="processing"
                            >Save changes</Button
                        >
                    </div>
                </Form>
            </CardContent></Card
        >
    </div>
</template>
