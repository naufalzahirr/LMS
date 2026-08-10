<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardCheck } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
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
import type { SelectOption } from '@/types/assessment';
import type {
    AssessmentAttemptStatus,
    AssessmentReviewAttempt,
    PaginationLink,
} from '@/types/assessment-attempt';

defineProps<{
    learningClass: { id: number; name: string };
    assignment: { id: number; title: string; competency: string };
    attempts: {
        data: AssessmentReviewAttempt[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { status: string };
    statuses: SelectOption<AssessmentAttemptStatus>[];
    backUrl: string;
}>();

function paginationLabel(label: string): string {
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}

function filter(status: unknown): void {
    router.get(
        window.location.pathname,
        status ? { status: String(status) } : {},
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="`${assignment.title} attempts`" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="`${assignment.title} attempts`"
                :description="`${learningClass.name} · ${assignment.competency}`"
            />
            <Button variant="outline" as-child
                ><Link :href="backUrl"><ArrowLeft /> Class</Link></Button
            >
        </div>
        <div class="grid max-w-xs gap-2">
            <Label>Status</Label>
            <Select
                :model-value="filters.status || 'all'"
                @update:model-value="filter($event === 'all' ? '' : $event)"
            >
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All statuses</SelectItem>
                    <SelectItem
                        v-for="status in statuses"
                        :key="status.value"
                        :value="status.value"
                        >{{ status.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
        </div>
        <Card class="gap-0 overflow-hidden py-0">
            <CardContent class="p-0">
                <div v-if="attempts.data.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Student</th>
                                <th class="px-5 py-3">Attempt</th>
                                <th class="px-5 py-3">Submitted</th>
                                <th class="px-5 py-3">Score</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="attempt in attempts.data"
                                :key="attempt.id"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium">
                                        {{ attempt.student }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ attempt.email }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    #{{ attempt.attempt_number }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ attempt.submitted_at ?? 'In progress' }}
                                </td>
                                <td class="px-5 py-4">
                                    {{
                                        attempt.earned_points ??
                                        attempt.auto_points ??
                                        '—'
                                    }}
                                    / {{ attempt.max_points
                                    }}<span v-if="attempt.percentage !== null">
                                        ({{ attempt.percentage }}%)</span
                                    >
                                </td>
                                <td class="px-5 py-4">
                                    <Badge variant="outline">{{
                                        attempt.status.replace('_', ' ')
                                    }}</Badge>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <Button
                                        v-if="attempt.grade_url"
                                        size="sm"
                                        variant="outline"
                                        as-child
                                        ><Link :href="attempt.grade_url"
                                            ><ClipboardCheck />
                                            {{
                                                attempt.status === 'graded'
                                                    ? 'Review'
                                                    : 'Grade'
                                            }}</Link
                                        ></Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    v-else
                    class="p-10 text-center text-sm text-muted-foreground"
                >
                    No attempts match this filter.
                </p>
            </CardContent>
        </Card>
        <div v-if="attempts.links.length > 3" class="flex flex-wrap gap-2">
            <Button
                v-for="link in attempts.links"
                :key="link.label"
                size="sm"
                :variant="link.active ? 'default' : 'outline'"
                :disabled="!link.url"
                as-child
            >
                <Link v-if="link.url" :href="link.url">{{
                    paginationLabel(link.label)
                }}</Link>
                <span v-else>{{ paginationLabel(link.label) }}</span>
            </Button>
        </div>
    </div>
</template>
