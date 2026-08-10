<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AssessmentController from '@/actions/App/Http/Controllers/Admin/AssessmentController';
import AssessmentFormFields from '@/components/admin/AssessmentFormFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index } from '@/routes/admin/assessments';
import type {
    AssessmentPurpose,
    AssessmentStatus,
    AuthoringOptions,
    SelectOption,
} from '@/types/assessment';

type EditableAssessment = {
    id: number;
    competency_id: number;
    title: string;
    code: string | null;
    description: string | null;
    purpose: AssessmentPurpose;
    status: AssessmentStatus;
    instructions: string | null;
    shuffle_questions: boolean;
};
defineProps<
    AuthoringOptions & {
        assessment: EditableAssessment;
        purposes: SelectOption<AssessmentPurpose>[];
    }
>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Assessments', href: index() },
            { title: 'Edit', href: index() },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${assessment.title}`" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="`Edit ${assessment.title}`"
            description="Update assessment metadata and instructions."
        />
        <Card class="max-w-4xl"
            ><CardContent
                ><Form
                    v-bind="AssessmentController.update.form(assessment.id)"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <AssessmentFormFields
                        :programs="programs"
                        :courses="courses"
                        :competencies="competencies"
                        :question-banks="questionBanks"
                        :purposes="purposes"
                        :errors="errors"
                        :initial="assessment"
                    />
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child
                            ><Link
                                :href="AssessmentController.show(assessment.id)"
                                >Cancel</Link
                            ></Button
                        ><Button type="submit" :disabled="processing"
                            >Save changes</Button
                        >
                    </div>
                </Form></CardContent
            ></Card
        >
    </div>
</template>
