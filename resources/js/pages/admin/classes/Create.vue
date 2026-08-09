<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import LearningClassController from '@/actions/App/Http/Controllers/Admin/LearningClassController';
import LearningClassFormFields from '@/components/admin/LearningClassFormFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, index } from '@/routes/admin/classes';
import type {
    DeliveryCourseOption,
    DeliveryProgramOption,
    LearningClassStatus,
    SelectOption,
} from '@/types/delivery';

defineProps<{
    programs: DeliveryProgramOption[];
    courses: DeliveryCourseOption[];
    statuses: SelectOption<LearningClassStatus>[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Classes', href: index() },
            { title: 'Create class', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Create class" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Create learning class"
            description="Create a delivery group for an existing course."
        />
        <Card class="max-w-3xl">
            <CardContent>
                <Form
                    v-bind="LearningClassController.store.form()"
                    reset-on-success
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <LearningClassFormFields
                        :programs="programs"
                        :courses="courses"
                        :statuses="statuses"
                        :errors="errors"
                    />
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child
                            ><Link :href="index()">Cancel</Link></Button
                        >
                        <Button type="submit" :disabled="processing"
                            >Create class</Button
                        >
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
