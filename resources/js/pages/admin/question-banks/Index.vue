<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Landmark, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, reactive } from 'vue';
import QuestionBankController from '@/actions/App/Http/Controllers/Admin/QuestionBankController';
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
import { create, index } from '@/routes/admin/question-banks';
import type {
    AcademicStatus,
    AcademicStatusOption,
    Paginated,
} from '@/types/academic';
import type { ProgramOption } from '@/types/academic';
import type { AssessmentCourseOption } from '@/types/assessment';

type BankRow = {
    id: number;
    name: string;
    code: string | null;
    course: string;
    program: string;
    questions_count: number;
    status: AcademicStatus;
    can_update: boolean;
    can_delete: boolean;
};
const props = defineProps<{
    programs: ProgramOption[];
    courses: AssessmentCourseOption[];
    questionBanks: Paginated<BankRow>;
    filters: {
        search: string;
        program_id: string;
        course_id: string;
        status: string;
    };
    statuses: AcademicStatusOption[];
    canManage: boolean;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Question banks', href: index() }] },
});
const filters = reactive({
    search: props.filters.search,
    program_id: props.filters.program_id || 'all',
    course_id: props.filters.course_id || 'all',
    status: props.filters.status || 'all',
});
const availableCourses = computed(() =>
    filters.program_id === 'all'
        ? props.courses
        : props.courses.filter(
              (item) => item.program_id === Number(filters.program_id),
          ),
);
const page = usePage();
const actionErrors = computed(() =>
    typeof page.props.errors?.question_bank === 'string'
        ? [page.props.errors.question_bank]
        : [],
);
function applyFilters(): void {
    router.get(
        index.url(),
        {
            search: filters.search || undefined,
            program_id:
                filters.program_id === 'all' ? undefined : filters.program_id,
            course_id:
                filters.course_id === 'all' ? undefined : filters.course_id,
            status: filters.status === 'all' ? undefined : filters.status,
        },
        { preserveState: true, replace: true },
    );
}
function resetFilters(): void {
    Object.assign(filters, {
        search: '',
        program_id: 'all',
        course_id: 'all',
        status: 'all',
    });
    applyFilters();
}
function remove(bank: BankRow): void {
    if (window.confirm(`Delete ${bank.name}?`)) {
        router.delete(QuestionBankController.destroy.url(bank.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Question banks" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Question banks"
                description="Organize reusable questions within each course."
            /><Button v-if="canManage" as-child
                ><Link :href="create()"><Plus /> Add bank</Link></Button
            >
        </div>
        <AlertError
            v-if="actionErrors.length"
            title="Question bank could not be deleted."
            :errors="actionErrors"
        />
        <form
            class="grid gap-3 rounded-lg border bg-card p-4 lg:grid-cols-[minmax(0,1fr)_12rem_14rem_10rem_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <Input
                v-model="filters.search"
                placeholder="Search bank name or code"
            />
            <Select v-model="filters.program_id"
                ><SelectTrigger class="w-full"><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectItem value="all">All programs</SelectItem
                    ><SelectItem
                        v-for="program in programs"
                        :key="program.id"
                        :value="program.id.toString()"
                        >{{ program.name }}</SelectItem
                    ></SelectContent
                ></Select
            >
            <Select v-model="filters.course_id"
                ><SelectTrigger class="w-full"><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectItem value="all">All courses</SelectItem
                    ><SelectItem
                        v-for="course in availableCourses"
                        :key="course.id"
                        :value="course.id.toString()"
                        >{{ course.name }}</SelectItem
                    ></SelectContent
                ></Select
            >
            <Select v-model="filters.status"
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
            >
            <Button type="submit"><Search /> Filter</Button
            ><Button type="button" variant="outline" @click="resetFilters"
                >Reset</Button
            >
        </form>
        <Card class="gap-0 overflow-hidden py-0"
            ><CardContent class="p-0"
                ><div v-if="questionBanks.data.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-6 py-3">Bank</th>
                                <th class="px-6 py-3">Course</th>
                                <th class="px-6 py-3">Program</th>
                                <th class="px-6 py-3">Questions</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="bank in questionBanks.data"
                                :key="bank.id"
                            >
                                <td class="px-6 py-4">
                                    <p class="font-medium">{{ bank.name }}</p>
                                    <p
                                        v-if="bank.code"
                                        class="font-mono text-xs text-muted-foreground"
                                    >
                                        {{ bank.code }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">{{ bank.course }}</td>
                                <td class="px-6 py-4">{{ bank.program }}</td>
                                <td class="px-6 py-4">
                                    {{ bank.questions_count }}
                                </td>
                                <td class="px-6 py-4">
                                    <Badge
                                        :variant="
                                            bank.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        >{{ bank.status }}</Badge
                                    >
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            v-if="bank.can_update"
                                            size="icon-sm"
                                            variant="outline"
                                            as-child
                                            ><Link
                                                :href="
                                                    QuestionBankController.edit(
                                                        bank.id,
                                                    )
                                                "
                                                aria-label="Edit bank"
                                                ><Pencil /></Link></Button
                                        ><Button
                                            v-if="bank.can_delete"
                                            size="icon-sm"
                                            variant="destructive"
                                            aria-label="Delete bank"
                                            @click="remove(bank)"
                                            ><Trash2
                                        /></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="flex flex-col items-center p-14 text-center">
                    <Landmark class="mb-4 size-10 text-muted-foreground" />
                    <p class="font-medium">No question banks found</p>
                </div></CardContent
            ></Card
        >
        <PaginationLinks
            :links="questionBanks.links"
            :from="questionBanks.from"
            :to="questionBanks.to"
            :total="questionBanks.total"
            item-label="question banks"
        />
    </div>
</template>
