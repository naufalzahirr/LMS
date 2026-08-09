<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import LearningClassController from '@/actions/App/Http/Controllers/Admin/LearningClassController';
import LearningClassFormFields from '@/components/admin/LearningClassFormFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index, show } from '@/routes/admin/classes';
import type {
    DeliveryCourseOption,
    DeliveryProgramOption,
    LearningClassStatus,
    SelectOption,
} from '@/types/delivery';

type EditableLearningClass = {
    id: number;
    course_id: number;
    name: string;
    code: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    status: LearningClassStatus;
};

defineProps<{
    learningClass: EditableLearningClass;
    programs: DeliveryProgramOption[];
    courses: DeliveryCourseOption[];
    statuses: SelectOption<LearningClassStatus>[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Classes', href: index() }] },
});
</script>

<template>
    <Head :title="`Edit ${learningClass.name}`" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Edit learning class"
            description="Update class identity, schedule, and lifecycle status."
        />
        <Card class="max-w-3xl">
            <CardContent>
                <Form
                    v-bind="
                        LearningClassController.update.form(learningClass.id)
                    "
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <LearningClassFormFields
                        :programs="programs"
                        :courses="courses"
                        :statuses="statuses"
                        :errors="errors"
                        :initial="learningClass"
                    />
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child
                            ><Link :href="show(learningClass.id)"
                                >Cancel</Link
                            ></Button
                        >
                        <Button type="submit" :disabled="processing"
                            >Save changes</Button
                        >
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
