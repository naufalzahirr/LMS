<script setup lang="ts">
import { Form, Link, router, usePage } from '@inertiajs/vue3';
import { ClipboardCheck, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import ClassAssessmentController from '@/actions/App/Http/Controllers/Admin/ClassAssessmentController';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    ClassAssessmentAssignment,
    AssessmentFeedbackMode,
    ClassAssessmentOption,
    ClassAssessmentStatus,
    SelectOption,
} from '@/types/assessment';

const props = defineProps<{
    classId: number;
    assignments: ClassAssessmentAssignment[];
    assessmentOptions: ClassAssessmentOption[];
    statuses: SelectOption<ClassAssessmentStatus>[];
    feedbackModes: SelectOption<AssessmentFeedbackMode>[];
}>();
const page = usePage();
const actionErrors = computed<string[]>(() => {
    const errors: string[] = [];

    for (const key of [
        'assessment_id',
        'opens_at',
        'closes_at',
        'max_attempts',
        'status',
        'feedback_mode',
        'assessment_assignment',
    ]) {
        const error = page.props.errors?.[key];

        if (typeof error === 'string') {
            errors.push(error);
        }
    }

    return errors;
});
function remove(item: ClassAssessmentAssignment): void {
    if (window.confirm(`Unassign ${item.title} from this class?`)) {
        router.delete(
            ClassAssessmentController.destroy.url([props.classId, item.id]),
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Card>
        <CardHeader><CardTitle>Class assessments</CardTitle></CardHeader>
        <CardContent class="space-y-6">
            <AlertError
                v-if="actionErrors.length"
                title="Assessment assignment failed."
                :errors="actionErrors"
            />
            <Form
                v-bind="ClassAssessmentController.store.form(classId)"
                class="grid items-end gap-3 md:grid-cols-2 xl:grid-cols-12"
                v-slot="{ errors, processing }"
            >
                <div class="grid min-w-0 gap-2 md:col-span-2 xl:col-span-4">
                    <Label for="assessment_id">Published assessment</Label
                    ><Select name="assessment_id" required
                        ><SelectTrigger
                            id="assessment_id"
                            class="w-full min-w-0"
                            ><SelectValue
                                placeholder="Select an assessment" /></SelectTrigger
                        ><SelectContent
                            ><SelectItem
                                v-for="item in assessmentOptions"
                                :key="item.id"
                                :value="item.id.toString()"
                                >{{ item.competency }} · {{ item.title }} ({{
                                    item.purpose
                                }})</SelectItem
                            ></SelectContent
                        ></Select
                    ><InputError :message="errors.assessment_id" />
                </div>
                <div class="grid min-w-0 gap-2 xl:col-span-4">
                    <Label for="opens_at">Opens at</Label
                    ><Input
                        id="opens_at"
                        name="opens_at"
                        type="datetime-local"
                    /><InputError :message="errors.opens_at" />
                </div>
                <div class="grid min-w-0 gap-2 xl:col-span-4">
                    <Label for="closes_at">Closes at</Label
                    ><Input
                        id="closes_at"
                        name="closes_at"
                        type="datetime-local"
                    /><InputError :message="errors.closes_at" />
                </div>
                <div class="grid min-w-0 gap-2 xl:col-span-2">
                    <Label for="max_attempts">Max attempts</Label
                    ><Input
                        id="max_attempts"
                        name="max_attempts"
                        type="number"
                        min="1"
                        :default-value="1"
                        required
                    /><InputError :message="errors.max_attempts" />
                </div>
                <div class="grid min-w-0 gap-2 xl:col-span-4">
                    <Label for="feedback_mode">Feedback</Label
                    ><Select
                        name="feedback_mode"
                        default-value="after_final_attempt"
                        required
                        ><SelectTrigger
                            id="feedback_mode"
                            class="w-full min-w-0"
                            ><SelectValue /></SelectTrigger
                        ><SelectContent
                            ><SelectItem
                                v-for="mode in feedbackModes"
                                :key="mode.value"
                                :value="mode.value"
                                >{{ mode.label }}</SelectItem
                            ></SelectContent
                        ></Select
                    >
                </div>
                <div class="grid min-w-0 gap-2 xl:col-span-3">
                    <Label for="assignment_status">Status</Label
                    ><Select name="status" default-value="active" required
                        ><SelectTrigger
                            id="assignment_status"
                            class="w-full min-w-0"
                            ><SelectValue /></SelectTrigger
                        ><SelectContent
                            ><SelectItem
                                v-for="status in statuses"
                                :key="status.value"
                                :value="status.value"
                                >{{ status.label }}</SelectItem
                            ></SelectContent
                        ></Select
                    >
                </div>
                <Button
                    type="submit"
                    class="w-full xl:col-span-3"
                    :disabled="processing || !assessmentOptions.length"
                    >Assign</Button
                >
            </Form>
            <div class="space-y-4">
                <div
                    v-for="assignment in assignments"
                    :key="assignment.id"
                    class="rounded-xl border p-4"
                >
                    <div
                        class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <p class="font-medium">{{ assignment.title }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ assignment.competency }} ·
                                {{ assignment.purpose }} ·
                                {{ assignment.questions_count }} questions ·
                                {{ assignment.total_points }} pts
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="outline" size="sm" as-child
                                ><Link :href="assignment.attempt_url"
                                    ><ClipboardCheck />
                                    {{ assignment.attempts_count }}
                                    attempts</Link
                                ></Button
                            >
                            <Badge
                                :variant="
                                    assignment.status === 'active'
                                        ? 'default'
                                        : 'secondary'
                                "
                                >{{ assignment.status }}</Badge
                            ><Button
                                size="icon-sm"
                                variant="destructive"
                                aria-label="Unassign assessment"
                                :disabled="assignment.attempts_count > 0"
                                @click="remove(assignment)"
                                ><Trash2
                            /></Button>
                        </div>
                    </div>
                    <Form
                        v-bind="
                            ClassAssessmentController.update.form([
                                classId,
                                assignment.id,
                            ])
                        "
                        class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-12"
                        v-slot="{ processing }"
                    >
                        <div class="grid min-w-0 gap-2 xl:col-span-4">
                            <Label :for="`opens-${assignment.id}`"
                                >Opens at</Label
                            ><Input
                                :id="`opens-${assignment.id}`"
                                name="opens_at"
                                type="datetime-local"
                                :default-value="assignment.opens_at ?? ''"
                            />
                        </div>
                        <div class="grid min-w-0 gap-2 xl:col-span-4">
                            <Label :for="`closes-${assignment.id}`"
                                >Closes at</Label
                            ><Input
                                :id="`closes-${assignment.id}`"
                                name="closes_at"
                                type="datetime-local"
                                :default-value="assignment.closes_at ?? ''"
                            />
                        </div>
                        <div class="grid min-w-0 gap-2 xl:col-span-4">
                            <Label :for="`attempts-${assignment.id}`"
                                >Attempts</Label
                            ><Input
                                :id="`attempts-${assignment.id}`"
                                name="max_attempts"
                                type="number"
                                min="1"
                                :default-value="assignment.max_attempts"
                                required
                            />
                        </div>
                        <div class="grid min-w-0 gap-2 xl:col-span-4">
                            <Label :for="`feedback-${assignment.id}`"
                                >Feedback</Label
                            ><Select
                                name="feedback_mode"
                                :default-value="assignment.feedback_mode"
                                required
                                ><SelectTrigger
                                    :id="`feedback-${assignment.id}`"
                                    class="w-full min-w-0"
                                    ><SelectValue /></SelectTrigger
                                ><SelectContent
                                    ><SelectItem
                                        v-for="mode in feedbackModes"
                                        :key="mode.value"
                                        :value="mode.value"
                                        >{{ mode.label }}</SelectItem
                                    ></SelectContent
                                ></Select
                            >
                        </div>
                        <div class="grid min-w-0 gap-2 xl:col-span-4">
                            <Label :for="`status-${assignment.id}`"
                                >Status</Label
                            ><Select
                                name="status"
                                :default-value="assignment.status"
                                required
                                ><SelectTrigger
                                    :id="`status-${assignment.id}`"
                                    class="w-full min-w-0"
                                    ><SelectValue /></SelectTrigger
                                ><SelectContent
                                    ><SelectItem
                                        v-for="status in statuses"
                                        :key="status.value"
                                        :value="status.value"
                                        >{{ status.label }}</SelectItem
                                    ></SelectContent
                                ></Select
                            >
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            class="w-full xl:col-span-4"
                            :disabled="processing"
                            >Save</Button
                        >
                    </Form>
                </div>
                <p
                    v-if="!assignments.length"
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    No assessment assigned to this class.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
