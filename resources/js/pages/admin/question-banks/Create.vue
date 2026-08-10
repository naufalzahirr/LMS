<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import QuestionBankController from '@/actions/App/Http/Controllers/Admin/QuestionBankController';
import QuestionBankFormFields from '@/components/admin/QuestionBankFormFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, index } from '@/routes/admin/question-banks';
import type { AcademicStatusOption } from '@/types/academic';
import type { AuthoringOptions } from '@/types/assessment';

defineProps<AuthoringOptions & { statuses: AcademicStatusOption[] }>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Question banks', href: index() },
            { title: 'Create', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Create question bank" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Create question bank"
            description="Create a reusable bank scoped to one course."
        />
        <Card class="max-w-3xl"
            ><CardContent>
                <Form
                    v-bind="QuestionBankController.store.form()"
                    class="space-y-6"
                    reset-on-success
                    v-slot="{ errors, processing }"
                >
                    <QuestionBankFormFields
                        :programs="programs"
                        :courses="courses"
                        :competencies="competencies"
                        :question-banks="questionBanks"
                        :statuses="statuses"
                        :errors="errors"
                    />
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child
                            ><Link :href="index()">Cancel</Link></Button
                        ><Button type="submit" :disabled="processing"
                            >Create bank</Button
                        >
                    </div>
                </Form>
            </CardContent></Card
        >
    </div>
</template>
