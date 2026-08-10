<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Cell = {
    competency_id: number;
    status: string;
    latest_score: string | null;
    best_score: string | null;
    required_score: string | null;
    remedial_url: string | null;
};

defineProps<{
    heatmap: {
        competencies: { id: number; name: string; prerequisites: string[] }[];
        students: {
            enrollment_id: number;
            student: string;
            email: string;
            competencies: Cell[];
        }[];
    };
}>();

const colors: Record<string, string> = {
    locked: 'bg-slate-100 text-slate-600 dark:bg-slate-900',
    learning: 'bg-blue-50 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
    ready_for_assessment:
        'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    needs_remedial:
        'bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-200',
    mastered:
        'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
};
</script>

<template>
    <Card class="gap-0 overflow-hidden py-0">
        <CardHeader class="py-5">
            <CardTitle>Class mastery</CardTitle>
            <p class="text-sm text-muted-foreground">
                Live competency state, scores, and remediation for every
                student.
            </p>
        </CardHeader>
        <CardContent class="p-0">
            <div v-if="heatmap.students.length" class="overflow-x-auto">
                <table class="w-full min-w-max text-sm">
                    <thead class="border-y bg-muted/40 text-left">
                        <tr>
                            <th class="sticky left-0 bg-muted px-5 py-3">
                                Student
                            </th>
                            <th
                                v-for="competency in heatmap.competencies"
                                :key="competency.id"
                                class="min-w-44 px-4 py-3"
                            >
                                <p>{{ competency.name }}</p>
                                <p
                                    v-if="competency.prerequisites.length"
                                    class="mt-1 text-xs font-normal text-muted-foreground"
                                >
                                    Requires
                                    {{ competency.prerequisites.join(', ') }}
                                </p>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="student in heatmap.students"
                            :key="student.enrollment_id"
                        >
                            <td class="sticky left-0 bg-background px-5 py-4">
                                <p class="font-medium">{{ student.student }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ student.email }}
                                </p>
                            </td>
                            <td
                                v-for="cell in student.competencies"
                                :key="cell.competency_id"
                                class="px-4 py-3"
                            >
                                <div
                                    class="space-y-1 rounded-md p-3"
                                    :class="
                                        colors[cell.status] ?? colors.learning
                                    "
                                >
                                    <p class="font-medium capitalize">
                                        {{ cell.status.replaceAll('_', ' ') }}
                                    </p>
                                    <p
                                        v-if="cell.required_score !== null"
                                        class="text-xs"
                                    >
                                        Best {{ cell.best_score ?? '—' }}% ·
                                        target {{ cell.required_score }}%
                                    </p>
                                    <Button
                                        v-if="cell.remedial_url"
                                        size="sm"
                                        variant="outline"
                                        as-child
                                    >
                                        <Link :href="cell.remedial_url"
                                            >Manage remedial</Link
                                        >
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="p-8 text-center text-sm text-muted-foreground">
                No students are enrolled in this class.
            </p>
        </CardContent>
    </Card>
</template>
