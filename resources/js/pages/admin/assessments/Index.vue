<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ClipboardCheck, Eye, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, reactive } from 'vue';
import AssessmentController from '@/actions/App/Http/Controllers/Admin/AssessmentController';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, index } from '@/routes/admin/assessments';
import type { Paginated } from '@/types/academic';
import type {
    AssessmentPurpose,
    AssessmentStatus,
    AuthoringOptions,
    SelectOption,
} from '@/types/assessment';

type AssessmentRow = {
    id: number;
    title: string;
    code: string | null;
    purpose: AssessmentPurpose;
    status: AssessmentStatus;
    competency: string;
    course: string;
    program: string;
    questions_count: number;
    total_points: string;
    can_update: boolean;
    can_delete: boolean;
};
const props = defineProps<
    AuthoringOptions & {
        assessments: Paginated<AssessmentRow>;
        filters: {
            search: string;
            program_id: string;
            course_id: string;
            competency_id: string;
            purpose: string;
            status: string;
        };
        purposes: SelectOption<AssessmentPurpose>[];
        statuses: SelectOption<AssessmentStatus>[];
        canManage: boolean;
    }
>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Assessments', href: index() }] },
});
const filters = reactive({
    search: props.filters.search,
    course_id: props.filters.course_id || 'all',
    competency_id: props.filters.competency_id || 'all',
    purpose: props.filters.purpose || 'all',
    status: props.filters.status || 'all',
});
const availableCompetencies = computed(() =>
    filters.course_id === 'all'
        ? props.competencies
        : props.competencies.filter(
              (item) => item.course_id === Number(filters.course_id),
          ),
);
const page = usePage();
const actionErrors = computed(() =>
    typeof page.props.errors?.assessment === 'string'
        ? [page.props.errors.assessment]
        : [],
);
function applyFilters(): void {
    router.get(
        index.url(),
        {
            search: filters.search || undefined,
            course_id:
                filters.course_id === 'all' ? undefined : filters.course_id,
            competency_id:
                filters.competency_id === 'all'
                    ? undefined
                    : filters.competency_id,
            purpose: filters.purpose === 'all' ? undefined : filters.purpose,
            status: filters.status === 'all' ? undefined : filters.status,
        },
        { preserveState: true, replace: true },
    );
}
function resetFilters(): void {
    Object.assign(filters, {
        search: '',
        course_id: 'all',
        competency_id: 'all',
        purpose: 'all',
        status: 'all',
    });
    applyFilters();
}
function remove(item: AssessmentRow): void {
    if (window.confirm(`Delete ${item.title}?`)) {
        router.delete(AssessmentController.destroy.url(item.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Assessments" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Assessments"
                description="Compose, publish, and reuse competency-aligned assessments."
            /><Button v-if="canManage" as-child
                ><Link :href="create()"><Plus /> Add assessment</Link></Button
            >
        </div>
        <AlertError
            v-if="actionErrors.length"
            title="Assessment action failed."
            :errors="actionErrors"
        />
        <form
            class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-3 xl:grid-cols-7"
            @submit.prevent="applyFilters"
        >
            <Input
                v-model="filters.search"
                placeholder="Search title or code"
            /><Select v-model="filters.course_id"
                ><SelectTrigger class="w-full"><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectItem value="all">All courses</SelectItem
                    ><SelectItem
                        v-for="course in courses"
                        :key="course.id"
                        :value="course.id.toString()"
                        >{{ course.name }}</SelectItem
                    ></SelectContent
                ></Select
            ><Select v-model="filters.competency_id"
                ><SelectTrigger class="w-full"><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectItem value="all">All competencies</SelectItem
                    ><SelectItem
                        v-for="item in availableCompetencies"
                        :key="item.id"
                        :value="item.id.toString()"
                        >{{ item.name }}</SelectItem
                    ></SelectContent
                ></Select
            ><Select v-model="filters.purpose"
                ><SelectTrigger class="w-full"><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectItem value="all">All purposes</SelectItem
                    ><SelectItem
                        v-for="purpose in purposes"
                        :key="purpose.value"
                        :value="purpose.value"
                        >{{ purpose.label }}</SelectItem
                    ></SelectContent
                ></Select
            ><Select v-model="filters.status"
                ><SelectTrigger class="w-full"><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectItem value="all">All statuses</SelectItem
                    ><SelectItem
                        v-for="status in statuses"
                        :key="status.value"
                        :value="status.value"
                        >{{ status.label }}</SelectItem
                    ></SelectContent
                ></Select
            ><Button type="submit"><Search /> Filter</Button
            ><Button type="button" variant="outline" @click="resetFilters"
                >Reset</Button
            >
        </form>
        <Card class="gap-0 overflow-hidden py-0"
            ><CardContent class="p-0"
                ><div v-if="assessments.data.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Assessment</th>
                                <th class="px-5 py-3">Competency</th>
                                <th class="px-5 py-3">Purpose</th>
                                <th class="px-5 py-3">Composition</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="assessment in assessments.data"
                                :key="assessment.id"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium">
                                        {{ assessment.title }}
                                    </p>
                                    <p
                                        v-if="assessment.code"
                                        class="font-mono text-xs text-muted-foreground"
                                    >
                                        {{ assessment.code }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <p>{{ assessment.competency }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ assessment.program }} /
                                        {{ assessment.course }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <Badge variant="outline">{{
                                        assessment.purpose
                                    }}</Badge>
                                </td>
                                <td class="px-5 py-4">
                                    {{ assessment.questions_count }} questions ·
                                    {{ assessment.total_points }} pts
                                </td>
                                <td class="px-5 py-4">
                                    <Badge
                                        :variant="
                                            assessment.status === 'published'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        >{{ assessment.status }}</Badge
                                    >
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            size="icon-sm"
                                            variant="ghost"
                                            as-child
                                            ><Link
                                                :href="
                                                    AssessmentController.show(
                                                        assessment.id,
                                                    )
                                                "
                                                aria-label="Open assessment"
                                                ><Eye /></Link></Button
                                        ><Button
                                            v-if="
                                                assessment.can_update &&
                                                assessment.status !== 'archived'
                                            "
                                            size="icon-sm"
                                            variant="outline"
                                            as-child
                                            ><Link
                                                :href="
                                                    AssessmentController.edit(
                                                        assessment.id,
                                                    )
                                                "
                                                aria-label="Edit assessment"
                                                ><Pencil /></Link></Button
                                        ><Button
                                            v-if="
                                                assessment.can_delete &&
                                                assessment.status === 'draft'
                                            "
                                            size="icon-sm"
                                            variant="destructive"
                                            aria-label="Delete assessment"
                                            @click="remove(assessment)"
                                            ><Trash2
                                        /></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="flex flex-col items-center p-14 text-center">
                    <ClipboardCheck
                        class="mb-4 size-10 text-muted-foreground"
                    />
                    <p class="font-medium">No assessments found</p>
                </div></CardContent
            ></Card
        >
        <PaginationLinks
            :links="assessments.links"
            :from="assessments.from"
            :to="assessments.to"
            :total="assessments.total"
            item-label="assessments"
        />
    </div>
</template>
