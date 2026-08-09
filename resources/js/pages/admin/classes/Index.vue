<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, GraduationCap, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import LearningClassController from '@/actions/App/Http/Controllers/Admin/LearningClassController';
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
import { create, index, show } from '@/routes/admin/classes';
import type {
    DeliveryCourseOption,
    DeliveryPaginator,
    DeliveryProgramOption,
    LearningClassStatus,
    SelectOption,
} from '@/types/delivery';

type ClassRow = {
    id: number;
    name: string;
    code: string;
    course: string;
    program: string;
    status: LearningClassStatus;
    start_date: string | null;
    end_date: string | null;
    active_students_count: number;
    tutors_count: number;
};

const props = defineProps<{
    classes: DeliveryPaginator<ClassRow>;
    filters: {
        search: string;
        program_id: string;
        course_id: string;
        status: string;
    };
    courses: DeliveryCourseOption[];
    programs: DeliveryProgramOption[];
    statuses: SelectOption<LearningClassStatus>[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Classes', href: index() }] },
});

const page = usePage();
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
              (course) => course.program_id === Number(filters.program_id),
          ),
);
const deletionErrors = computed(() => {
    const error = page.props.errors?.learning_class;

    return typeof error === 'string' ? [error] : [];
});

watch(
    () => filters.program_id,
    () => {
        if (
            filters.course_id !== 'all' &&
            !availableCourses.value.some(
                (course) => course.id === Number(filters.course_id),
            )
        ) {
            filters.course_id = 'all';
        }
    },
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
    filters.search = '';
    filters.program_id = 'all';
    filters.course_id = 'all';
    filters.status = 'all';
    applyFilters();
}

function removeClass(learningClass: ClassRow): void {
    if (!window.confirm(`Delete ${learningClass.name}?`)) {
        return;
    }

    router.delete(LearningClassController.destroy.url(learningClass.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Classes" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Learning classes"
                description="Manage course delivery groups, rosters, and tutor assignments."
            />
            <Button as-child
                ><Link :href="create()"><Plus /> Add class</Link></Button
            >
        </div>
        <AlertError
            v-if="deletionErrors.length"
            title="Class could not be deleted."
            :errors="deletionErrors"
        />
        <form
            class="grid gap-3 rounded-lg border bg-card p-4 xl:grid-cols-[1fr_14rem_16rem_12rem_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <Input v-model="filters.search" placeholder="Search name or code" />
            <Select v-model="filters.program_id">
                <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All programs</SelectItem>
                    <SelectItem
                        v-for="program in programs"
                        :key="program.id"
                        :value="program.id.toString()"
                    >
                        {{ program.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="filters.course_id">
                <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All courses</SelectItem>
                    <SelectItem
                        v-for="course in availableCourses"
                        :key="course.id"
                        :value="course.id.toString()"
                    >
                        {{ course.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="filters.status">
                <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All statuses</SelectItem>
                    <SelectItem
                        v-for="status in statuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Button type="submit"><Search /> Filter</Button>
            <Button type="button" variant="outline" @click="resetFilters"
                >Reset</Button
            >
        </form>
        <Card class="gap-0 overflow-hidden py-0">
            <CardContent class="p-0">
                <div v-if="classes.data.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Class</th>
                                <th class="px-5 py-3">Course</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Schedule</th>
                                <th class="px-5 py-3">Roster</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="item in classes.data"
                                :key="item.id"
                                class="hover:bg-muted/30"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium">{{ item.name }}</p>
                                    <p class="text-muted-foreground">
                                        {{ item.code }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <p>{{ item.course }}</p>
                                    <p class="text-muted-foreground">
                                        {{ item.program }}
                                    </p>
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
                                <td class="px-5 py-4">
                                    {{ item.start_date ?? '—' }} –
                                    {{ item.end_date ?? '—' }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ item.active_students_count }} students ·
                                    {{ item.tutors_count }} tutors
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            size="icon-sm"
                                            variant="outline"
                                            as-child
                                            ><Link
                                                :href="show(item.id)"
                                                :aria-label="`Open ${item.name}`"
                                                ><Eye /></Link
                                        ></Button>
                                        <Button
                                            size="icon-sm"
                                            variant="outline"
                                            as-child
                                            ><Link
                                                :href="
                                                    LearningClassController.edit(
                                                        item.id,
                                                    )
                                                "
                                                :aria-label="`Edit ${item.name}`"
                                                ><Pencil /></Link
                                        ></Button>
                                        <Button
                                            size="icon-sm"
                                            variant="destructive"
                                            :aria-label="`Delete ${item.name}`"
                                            @click="removeClass(item)"
                                            ><Trash2
                                        /></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-else
                    class="flex flex-col items-center px-6 py-16 text-center"
                >
                    <GraduationCap class="mb-4 size-10 text-muted-foreground" />
                    <p class="font-medium">No classes found</p>
                </div>
            </CardContent>
        </Card>
        <PaginationLinks
            :links="classes.links"
            :from="classes.from"
            :to="classes.to"
            :total="classes.total"
            item-label="classes"
        />
    </div>
</template>
