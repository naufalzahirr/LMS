<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ParentStudentRelationshipController from '@/actions/App/Http/Controllers/Admin/ParentStudentRelationshipController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, index } from '@/routes/admin/parent-students';
import type {
    DeliveryUserOption,
    ParentRelationshipType,
    SelectOption,
} from '@/types/delivery';

defineProps<{
    parents: DeliveryUserOption[];
    students: DeliveryUserOption[];
    relationshipTypes: SelectOption<ParentRelationshipType>[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Parent–students', href: index() },
            { title: 'Create', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Link parent and student" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Link parent and student"
            description="Connect one Parent account to one Student account."
        />
        <Card class="max-w-2xl"
            ><CardContent>
                <Form
                    v-bind="ParentStudentRelationshipController.store.form()"
                    reset-on-success
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="parent_id">Parent</Label
                        ><Select name="parent_id" required
                            ><SelectTrigger id="parent_id" class="w-full"
                                ><SelectValue
                                    placeholder="Select a parent" /></SelectTrigger
                            ><SelectContent
                                ><SelectItem
                                    v-for="parent in parents"
                                    :key="parent.id"
                                    :value="parent.id.toString()"
                                    >{{ parent.name }} —
                                    {{ parent.email }}</SelectItem
                                ></SelectContent
                            ></Select
                        ><InputError :message="errors.parent_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="student_id">Student</Label
                        ><Select name="student_id" required
                            ><SelectTrigger id="student_id" class="w-full"
                                ><SelectValue
                                    placeholder="Select a student" /></SelectTrigger
                            ><SelectContent
                                ><SelectItem
                                    v-for="student in students"
                                    :key="student.id"
                                    :value="student.id.toString()"
                                    >{{ student.name }} —
                                    {{ student.email }}</SelectItem
                                ></SelectContent
                            ></Select
                        ><InputError :message="errors.student_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="relationship_type">Relationship</Label
                        ><Select name="relationship_type" required
                            ><SelectTrigger
                                id="relationship_type"
                                class="w-full"
                                ><SelectValue
                                    placeholder="Select relationship" /></SelectTrigger
                            ><SelectContent
                                ><SelectItem
                                    v-for="type in relationshipTypes"
                                    :key="type.value"
                                    :value="type.value"
                                    >{{ type.label }}</SelectItem
                                ></SelectContent
                            ></Select
                        ><InputError :message="errors.relationship_type" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" as-child
                            ><Link :href="index()">Cancel</Link></Button
                        ><Button type="submit" :disabled="processing"
                            >Create relationship</Button
                        >
                    </div>
                </Form>
            </CardContent></Card
        >
    </div>
</template>
