<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AssessmentController from '@/actions/App/Http/Controllers/Admin/AssessmentController';
import AssessmentFormFields from '@/components/admin/AssessmentFormFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, index } from '@/routes/admin/assessments';
import type {
    AssessmentPurpose,
    AuthoringOptions,
    SelectOption,
} from '@/types/assessment';

defineProps<
    AuthoringOptions & { purposes: SelectOption<AssessmentPurpose>[] }
>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Assessments', href: index() },
            { title: 'Create', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Create assessment" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Create assessment"
            description="Define a competency-aligned draft, then compose its questions."
        />
        <Card class="max-w-4xl"
            ><CardContent
                ><Form
                    v-bind="AssessmentController.store.form()"
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
                    />
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child
                            ><Link :href="index()">Cancel</Link></Button
                        ><Button type="submit" :disabled="processing"
                            >Create draft</Button
                        >
                    </div>
                </Form></CardContent
            ></Card
        >
    </div>
</template>
