<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, LibraryBig, NotebookText, Target } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/tutor/classes';
import type { ClassAssessmentAssignment } from '@/types/assessment';
import type { EnrollmentStatus, LearningClassStatus } from '@/types/delivery';

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
    student: { name: string; email: string };
    status: EnrollmentStatus;
    completed_lessons: number;
    total_lessons: number;
    progress_percentage: number;
};
defineProps<{
    learningClass: ClassDetails;
    enrollments: EnrollmentRow[];
    contentSummary: { competencies: number; modules: number; lessons: number };
    assessmentAssignments: ClassAssessmentAssignment[];
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'My classes', href: index() }] },
});
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
            /><Button variant="outline" as-child
                ><Link :href="index()"><ArrowLeft /> My classes</Link></Button
            >
        </div>
        <Card
            ><CardContent class="grid gap-5 pt-2 sm:grid-cols-2 lg:grid-cols-4"
                ><div>
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
                </p></CardContent
            ></Card
        >
        <div class="grid gap-4 sm:grid-cols-3">
            <Card
                ><CardContent class="flex items-center gap-4"
                    ><Target class="size-8 text-muted-foreground" />
                    <div>
                        <p class="text-2xl font-semibold">
                            {{ contentSummary.competencies }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Competencies
                        </p>
                    </div></CardContent
                ></Card
            ><Card
                ><CardContent class="flex items-center gap-4"
                    ><LibraryBig class="size-8 text-muted-foreground" />
                    <div>
                        <p class="text-2xl font-semibold">
                            {{ contentSummary.modules }}
                        </p>
                        <p class="text-sm text-muted-foreground">Modules</p>
                    </div></CardContent
                ></Card
            ><Card
                ><CardContent class="flex items-center gap-4"
                    ><NotebookText class="size-8 text-muted-foreground" />
                    <div>
                        <p class="text-2xl font-semibold">
                            {{ contentSummary.lessons }}
                        </p>
                        <p class="text-sm text-muted-foreground">Lessons</p>
                    </div></CardContent
                ></Card
            >
        </div>
        <Card class="gap-0 overflow-hidden py-0">
            <CardHeader class="py-5"
                ><CardTitle>Assigned assessments</CardTitle></CardHeader
            >
            <CardContent class="p-0">
                <div
                    v-if="assessmentAssignments.length"
                    class="overflow-x-auto"
                >
                    <table class="w-full text-sm">
                        <thead class="border-y bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Assessment</th>
                                <th class="px-5 py-3">Composition</th>
                                <th class="px-5 py-3">Window</th>
                                <th class="px-5 py-3">Attempts</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="item in assessmentAssignments"
                                :key="item.id"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium">{{ item.title }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ item.competency }} ·
                                        {{ item.purpose }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    {{ item.questions_count }} questions ·
                                    {{ item.total_points }} pts
                                </td>
                                <td class="px-5 py-4">
                                    <p>{{ item.opens_at ?? 'Immediately' }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        to {{ item.closes_at ?? 'No deadline' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    {{ item.max_attempts }}
                                </td>
                                <td class="px-5 py-4">
                                    <Badge
                                        :variant="
                                            item.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        >{{ item.status }}</Badge
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="p-8 text-center text-sm text-muted-foreground">
                    No assessment assigned to this class.
                </p>
            </CardContent>
        </Card>
        <Card class="gap-0 overflow-hidden py-0"
            ><CardHeader class="py-5"><CardTitle>Roster</CardTitle></CardHeader
            ><CardContent class="p-0"
                ><div v-if="enrollments.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-y bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Student</th>
                                <th class="px-5 py-3">Email</th>
                                <th class="px-5 py-3">Enrollment status</th>
                                <th class="px-5 py-3">Completed lessons</th>
                                <th class="px-5 py-3">Progress</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="enrollment in enrollments"
                                :key="enrollment.id"
                            >
                                <td class="px-5 py-4 font-medium">
                                    {{ enrollment.student.name }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ enrollment.student.email }}
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
                                <td class="px-5 py-4 font-medium">
                                    {{ enrollment.completed_lessons }} /
                                    {{ enrollment.total_lessons }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ enrollment.progress_percentage }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="p-8 text-center text-sm text-muted-foreground">
                    No enrollment history yet.
                </p></CardContent
            ></Card
        >
    </div>
</template>
