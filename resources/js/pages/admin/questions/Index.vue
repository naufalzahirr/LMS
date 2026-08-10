<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, HelpCircle, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, reactive } from 'vue';
import QuestionController from '@/actions/App/Http/Controllers/Admin/QuestionController';
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
import { create, index } from '@/routes/admin/questions';
import type {
    AcademicStatus,
    AcademicStatusOption,
    Paginated,
} from '@/types/academic';
import type {
    AuthoringOptions,
    QuestionType,
    SelectOption,
} from '@/types/assessment';

type QuestionRow = {
    id: number;
    prompt: string;
    question_type: QuestionType;
    competency: string;
    course: string;
    program: string;
    bank: string;
    default_points: string;
    status: AcademicStatus;
    can_update: boolean;
    can_delete: boolean;
};
const props = defineProps<
    AuthoringOptions & {
        questions: Paginated<QuestionRow>;
        filters: {
            search: string;
            program_id: string;
            course_id: string;
            competency_id: string;
            question_bank_id: string;
            question_type: string;
            status: string;
        };
        questionTypes: SelectOption<QuestionType>[];
        statuses: AcademicStatusOption[];
        canManage: boolean;
    }
>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Questions', href: index() }] },
});
const filters = reactive({
    search: props.filters.search,
    course_id: props.filters.course_id || 'all',
    competency_id: props.filters.competency_id || 'all',
    question_bank_id: props.filters.question_bank_id || 'all',
    question_type: props.filters.question_type || 'all',
    status: props.filters.status || 'all',
});
const filteredCompetencies = computed(() =>
    filters.course_id === 'all'
        ? props.competencies
        : props.competencies.filter(
              (item) => item.course_id === Number(filters.course_id),
          ),
);
const filteredBanks = computed(() =>
    filters.course_id === 'all'
        ? props.questionBanks
        : props.questionBanks.filter(
              (item) => item.course_id === Number(filters.course_id),
          ),
);
const page = usePage();
const actionErrors = computed(() =>
    typeof page.props.errors?.question === 'string'
        ? [page.props.errors.question]
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
            question_bank_id:
                filters.question_bank_id === 'all'
                    ? undefined
                    : filters.question_bank_id,
            question_type:
                filters.question_type === 'all'
                    ? undefined
                    : filters.question_type,
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
        question_bank_id: 'all',
        question_type: 'all',
        status: 'all',
    });
    applyFilters();
}
function remove(item: QuestionRow): void {
    if (window.confirm('Delete this question?')) {
        router.delete(QuestionController.destroy.url(item.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Questions" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Questions"
                description="Author reusable questions and normalized answer keys."
            /><Button v-if="canManage" as-child
                ><Link :href="create()"><Plus /> Add question</Link></Button
            >
        </div>
        <AlertError
            v-if="actionErrors.length"
            title="Question could not be deleted."
            :errors="actionErrors"
        />
        <form
            class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-3 xl:grid-cols-7"
            @submit.prevent="applyFilters"
        >
            <Input v-model="filters.search" placeholder="Search prompt" />
            <Select v-model="filters.course_id"
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
            >
            <Select v-model="filters.competency_id"
                ><SelectTrigger class="w-full"><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectItem value="all">All competencies</SelectItem
                    ><SelectItem
                        v-for="item in filteredCompetencies"
                        :key="item.id"
                        :value="item.id.toString()"
                        >{{ item.name }}</SelectItem
                    ></SelectContent
                ></Select
            >
            <Select v-model="filters.question_bank_id"
                ><SelectTrigger class="w-full"><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectItem value="all">All banks</SelectItem
                    ><SelectItem
                        v-for="bank in filteredBanks"
                        :key="bank.id"
                        :value="bank.id.toString()"
                        >{{ bank.name }}</SelectItem
                    ></SelectContent
                ></Select
            >
            <Select v-model="filters.question_type"
                ><SelectTrigger class="w-full"><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectItem value="all">All types</SelectItem
                    ><SelectItem
                        v-for="type in questionTypes"
                        :key="type.value"
                        :value="type.value"
                        >{{ type.label }}</SelectItem
                    ></SelectContent
                ></Select
            >
            <Button type="submit"><Search /> Filter</Button
            ><Button type="button" variant="outline" @click="resetFilters"
                >Reset</Button
            >
        </form>
        <Card class="gap-0 overflow-hidden py-0"
            ><CardContent class="p-0"
                ><div v-if="questions.data.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Question</th>
                                <th class="px-5 py-3">Context</th>
                                <th class="px-5 py-3">Type</th>
                                <th class="px-5 py-3">Points</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="question in questions.data"
                                :key="question.id"
                            >
                                <td class="max-w-xl px-5 py-4 font-medium">
                                    <p class="line-clamp-2">
                                        {{ question.prompt }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <p>{{ question.competency }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ question.course }} /
                                        {{ question.bank }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <Badge variant="outline">{{
                                        question.question_type
                                    }}</Badge>
                                </td>
                                <td class="px-5 py-4">
                                    {{ question.default_points }}
                                </td>
                                <td class="px-5 py-4">
                                    <Badge
                                        :variant="
                                            question.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        >{{ question.status }}</Badge
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
                                                    QuestionController.show(
                                                        question.id,
                                                    )
                                                "
                                                aria-label="Preview question"
                                                ><Eye /></Link></Button
                                        ><Button
                                            v-if="question.can_update"
                                            size="icon-sm"
                                            variant="outline"
                                            as-child
                                            ><Link
                                                :href="
                                                    QuestionController.edit(
                                                        question.id,
                                                    )
                                                "
                                                aria-label="Edit question"
                                                ><Pencil /></Link></Button
                                        ><Button
                                            v-if="question.can_delete"
                                            size="icon-sm"
                                            variant="destructive"
                                            aria-label="Delete question"
                                            @click="remove(question)"
                                            ><Trash2
                                        /></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="flex flex-col items-center p-14 text-center">
                    <HelpCircle class="mb-4 size-10 text-muted-foreground" />
                    <p class="font-medium">No questions found</p>
                </div></CardContent
            ></Card
        >
        <PaginationLinks
            :links="questions.links"
            :from="questions.from"
            :to="questions.to"
            :total="questions.total"
            item-label="questions"
        />
    </div>
</template>
