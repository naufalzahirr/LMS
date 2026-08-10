<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Pencil,
    RotateCcw,
    Trash2,
    UserMinus,
} from '@lucide/vue';
import { computed } from 'vue';
import EnrollmentController from '@/actions/App/Http/Controllers/Admin/EnrollmentController';
import LearningClassController from '@/actions/App/Http/Controllers/Admin/LearningClassController';
import TutorAssignmentController from '@/actions/App/Http/Controllers/Admin/TutorAssignmentController';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/admin/classes';
import type {
    DeliveryUserOption,
    EnrollmentStatus,
    LearningClassStatus,
} from '@/types/delivery';

type ClassDetails = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    course: string;
    program: string;
    status: LearningClassStatus;
    start_date: string | null;
    end_date: string | null;
};
type EnrollmentRow = {
    id: number;
    student: DeliveryUserOption;
    status: EnrollmentStatus;
    enrolled_at: string;
    completed_at: string | null;
    completed_lessons: number;
    total_lessons: number;
    progress_percentage: number;
};

defineProps<{
    learningClass: ClassDetails;
    enrollments: EnrollmentRow[];
    tutors: DeliveryUserOption[];
    studentOptions: DeliveryUserOption[];
    tutorOptions: DeliveryUserOption[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Classes', href: index() }] },
});

const page = usePage();
const actionErrors = computed<string[]>(() => {
    const errors: string[] = [];

    for (const key of [
        'learning_class',
        'student_id',
        'tutor_id',
        'enrollment',
    ]) {
        const error = page.props.errors?.[key];

        if (typeof error === 'string') {
            errors.push(error);
        }
    }

    return errors;
});

function enrollmentAction(
    action: 'withdraw' | 'reactivate' | 'complete',
    classId: number,
    enrollmentId: number,
): void {
    router.patch(
        EnrollmentController[action].url([classId, enrollmentId]),
        {},
        { preserveScroll: true },
    );
}

function unassignTutor(classId: number, tutor: DeliveryUserOption): void {
    if (!window.confirm(`Unassign ${tutor.name}?`)) {
        return;
    }

    router.delete(TutorAssignmentController.destroy.url([classId, tutor.id]), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="learningClass.name" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="learningClass.name"
                :description="`${learningClass.program} / ${learningClass.course}`"
            />
            <div class="flex gap-2">
                <Button variant="outline" as-child
                    ><Link :href="index()"><ArrowLeft /> Classes</Link></Button
                >
                <Button as-child
                    ><Link
                        :href="LearningClassController.edit(learningClass.id)"
                        ><Pencil /> Edit</Link
                    ></Button
                >
            </div>
        </div>
        <AlertError
            v-if="actionErrors.length"
            title="The class could not be updated."
            :errors="actionErrors"
        />
        <Card>
            <CardContent class="grid gap-5 pt-2 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs font-medium text-muted-foreground">
                        Code
                    </p>
                    <p class="mt-1">{{ learningClass.code }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-muted-foreground">
                        Status
                    </p>
                    <Badge
                        class="mt-1"
                        :variant="
                            learningClass.status === 'active'
                                ? 'default'
                                : 'secondary'
                        "
                        >{{ learningClass.status }}</Badge
                    >
                </div>
                <div>
                    <p class="text-xs font-medium text-muted-foreground">
                        Starts
                    </p>
                    <p class="mt-1">
                        {{ learningClass.start_date ?? 'Not set' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium text-muted-foreground">
                        Ends
                    </p>
                    <p class="mt-1">
                        {{ learningClass.end_date ?? 'Not set' }}
                    </p>
                </div>
                <p
                    v-if="learningClass.description"
                    class="text-sm text-muted-foreground sm:col-span-2 lg:col-span-4"
                >
                    {{ learningClass.description }}
                </p>
            </CardContent>
        </Card>
        <div class="grid gap-6 xl:grid-cols-2">
            <Card>
                <CardHeader
                    ><CardTitle>Tutor assignments</CardTitle></CardHeader
                >
                <CardContent class="space-y-5">
                    <Form
                        v-bind="
                            TutorAssignmentController.store.form(
                                learningClass.id,
                            )
                        "
                        class="flex items-end gap-3"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid flex-1 gap-2">
                            <Label for="tutor_id">Assign tutor</Label>
                            <Select name="tutor_id" required>
                                <SelectTrigger id="tutor_id" class="w-full"
                                    ><SelectValue placeholder="Select a tutor"
                                /></SelectTrigger>
                                <SelectContent
                                    ><SelectItem
                                        v-for="tutor in tutorOptions"
                                        :key="tutor.id"
                                        :value="tutor.id.toString()"
                                        >{{ tutor.name }} —
                                        {{ tutor.email }}</SelectItem
                                    ></SelectContent
                                >
                            </Select>
                            <InputError :message="errors.tutor_id" />
                        </div>
                        <Button
                            type="submit"
                            :disabled="processing || !tutorOptions.length"
                            >Assign</Button
                        >
                    </Form>
                    <div class="divide-y rounded-lg border">
                        <div
                            v-for="tutor in tutors"
                            :key="tutor.id"
                            class="flex items-center justify-between gap-3 p-3"
                        >
                            <div>
                                <p class="font-medium">{{ tutor.name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ tutor.email }}
                                </p>
                            </div>
                            <Button
                                size="icon-sm"
                                variant="ghost"
                                :aria-label="`Unassign ${tutor.name}`"
                                @click="unassignTutor(learningClass.id, tutor)"
                                ><UserMinus
                            /></Button>
                        </div>
                        <p
                            v-if="!tutors.length"
                            class="p-4 text-sm text-muted-foreground"
                        >
                            No tutor assigned.
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader><CardTitle>Enroll a student</CardTitle></CardHeader>
                <CardContent>
                    <Form
                        v-bind="
                            EnrollmentController.store.form(learningClass.id)
                        "
                        class="flex items-end gap-3"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid flex-1 gap-2">
                            <Label for="student_id">Student</Label>
                            <Select name="student_id" required>
                                <SelectTrigger id="student_id" class="w-full"
                                    ><SelectValue
                                        placeholder="Select a student"
                                /></SelectTrigger>
                                <SelectContent
                                    ><SelectItem
                                        v-for="student in studentOptions"
                                        :key="student.id"
                                        :value="student.id.toString()"
                                        >{{ student.name }} —
                                        {{ student.email }}</SelectItem
                                    ></SelectContent
                                >
                            </Select>
                            <InputError :message="errors.student_id" />
                        </div>
                        <Button
                            type="submit"
                            :disabled="processing || !studentOptions.length"
                            >Enroll</Button
                        >
                    </Form>
                </CardContent>
            </Card>
        </div>
        <Card class="gap-0 overflow-hidden py-0">
            <CardHeader class="py-5"
                ><CardTitle>Enrollment history</CardTitle></CardHeader
            >
            <CardContent class="p-0">
                <div v-if="enrollments.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-y bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Student</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Enrolled</th>
                                <th class="px-5 py-3">Completed</th>
                                <th class="px-5 py-3">Lesson progress</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="enrollment in enrollments"
                                :key="enrollment.id"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium">
                                        {{ enrollment.student.name }}
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{ enrollment.student.email }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <Badge
                                        :variant="
                                            enrollment.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        >{{ enrollment.status }}</Badge
                                    >
                                </td>
                                <td class="px-5 py-4">
                                    {{ enrollment.enrolled_at }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ enrollment.completed_at ?? '—' }}
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium">
                                        {{ enrollment.completed_lessons }} /
                                        {{ enrollment.total_lessons }}
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{ enrollment.progress_percentage }}%
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            v-if="
                                                enrollment.status === 'active'
                                            "
                                            size="sm"
                                            variant="outline"
                                            @click="
                                                enrollmentAction(
                                                    'complete',
                                                    learningClass.id,
                                                    enrollment.id,
                                                )
                                            "
                                            ><CheckCircle2 /> Complete</Button
                                        >
                                        <Button
                                            v-if="
                                                enrollment.status === 'active'
                                            "
                                            size="sm"
                                            variant="destructive"
                                            @click="
                                                enrollmentAction(
                                                    'withdraw',
                                                    learningClass.id,
                                                    enrollment.id,
                                                )
                                            "
                                            ><Trash2 /> Withdraw</Button
                                        >
                                        <Button
                                            v-else
                                            size="sm"
                                            variant="outline"
                                            @click="
                                                enrollmentAction(
                                                    'reactivate',
                                                    learningClass.id,
                                                    enrollment.id,
                                                )
                                            "
                                            ><RotateCcw /> Reactivate</Button
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="p-8 text-center text-sm text-muted-foreground">
                    No enrollment history yet.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
