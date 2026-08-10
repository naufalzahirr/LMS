<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ParentClassProgress } from '@/types/parent-progress';

defineProps<{ learningClass: ParentClassProgress }>();

function label(value: string): string {
    return value.replaceAll('_', ' ');
}
</script>

<template>
    <Card class="gap-0 overflow-hidden py-0">
        <CardHeader class="space-y-3 py-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <CardTitle>{{ learningClass.name }}</CardTitle>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ learningClass.program }} · {{ learningClass.course }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Badge variant="outline">{{
                        label(learningClass.enrollment_status)
                    }}</Badge>
                    <Badge variant="secondary">{{
                        label(learningClass.class_status)
                    }}</Badge>
                </div>
            </div>
            <p class="text-sm text-muted-foreground">
                Tutors: {{ learningClass.tutors.join(', ') || 'Not assigned' }}
            </p>
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span>Lesson completion</span>
                    <span class="font-medium">
                        {{ learningClass.lesson_progress.completed }} /
                        {{ learningClass.lesson_progress.total }} ·
                        {{ learningClass.lesson_progress.percentage }}%
                    </span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full bg-primary"
                        :style="{
                            width: `${learningClass.lesson_progress.percentage}%`,
                        }"
                    ></div>
                </div>
            </div>
        </CardHeader>
        <CardContent class="space-y-6 border-t py-5">
            <section>
                <h3 class="mb-3 font-semibold">Mastery progress</h3>
                <div
                    v-if="learningClass.mastery.length"
                    class="overflow-x-auto rounded-lg border"
                >
                    <table class="w-full min-w-[44rem] text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-4 py-3">Competency</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Latest</th>
                                <th class="px-4 py-3">Best</th>
                                <th class="px-4 py-3">Required</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="cell in learningClass.mastery"
                                :key="cell.id"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ cell.name }}
                                    <div
                                        v-if="cell.status === 'needs_remedial'"
                                        class="mt-1 font-normal text-amber-700 dark:text-amber-300"
                                    >
                                        Remedial learning is required.
                                        <span
                                            v-if="cell.remedial_lessons.length"
                                        >
                                            Assigned:
                                            {{
                                                cell.remedial_lessons.join(
                                                    ', ',
                                                )
                                            }}.
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 capitalize">
                                    <Badge
                                        :variant="
                                            cell.status === 'mastered'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{ label(cell.status) }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    {{ cell.latest_score ?? '—'
                                    }}<span v-if="cell.latest_score">%</span>
                                </td>
                                <td class="px-4 py-3">
                                    {{ cell.best_score ?? '—'
                                    }}<span v-if="cell.best_score">%</span>
                                </td>
                                <td class="px-4 py-3">
                                    {{ cell.required_score ?? '—'
                                    }}<span v-if="cell.required_score">%</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No active competencies are available.
                </p>
            </section>

            <section>
                <h3 class="mb-3 font-semibold">Assessment history</h3>
                <div
                    v-if="learningClass.assessments.length"
                    class="overflow-x-auto rounded-lg border"
                >
                    <table class="w-full min-w-[48rem] text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-4 py-3">Assessment</th>
                                <th class="px-4 py-3">Purpose</th>
                                <th class="px-4 py-3">Attempt</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Score</th>
                                <th class="px-4 py-3">Submitted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="attempt in learningClass.assessments"
                                :key="`${attempt.assessment}-${attempt.attempt}`"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ attempt.assessment }}
                                </td>
                                <td class="px-4 py-3 capitalize">
                                    {{ label(attempt.purpose) }}
                                </td>
                                <td class="px-4 py-3">
                                    #{{ attempt.attempt }}
                                </td>
                                <td class="px-4 py-3 capitalize">
                                    {{ label(attempt.status) }}
                                </td>
                                <td class="px-4 py-3">
                                    <template
                                        v-if="attempt.status === 'graded'"
                                    >
                                        {{ attempt.score }} ({{
                                            attempt.percentage
                                        }}%)
                                    </template>
                                    <span
                                        v-else-if="
                                            attempt.status === 'pending_grading'
                                        "
                                        >Waiting for grading</span
                                    >
                                    <span v-else>In progress</span>
                                </td>
                                <td class="px-4 py-3">
                                    {{ attempt.submitted_at ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No assessment attempts yet.
                </p>
            </section>
        </CardContent>
    </Card>
</template>
