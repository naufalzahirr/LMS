<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, GraduationCap, Search } from '@lucide/vue';
import { reactive } from 'vue';
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
import { index, show } from '@/routes/tutor/classes';
import type {
    DeliveryPaginator,
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
};
const props = defineProps<{
    classes: DeliveryPaginator<ClassRow>;
    filters: { search: string; status: string };
    statuses: SelectOption<LearningClassStatus>[];
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'My classes', href: index() }] },
});
const filters = reactive({
    search: props.filters.search,
    status: props.filters.status || 'all',
});
function applyFilters(): void {
    router.get(
        index.url(),
        {
            search: filters.search || undefined,
            status: filters.status === 'all' ? undefined : filters.status,
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="My classes" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="My classes"
            description="View only the learning classes assigned to you."
        />
        <form
            class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-[1fr_14rem_auto]"
            @submit.prevent="applyFilters"
        >
            <Input
                v-model="filters.search"
                placeholder="Search name or code"
            /><Select v-model="filters.status"
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
            ><Button type="submit"><Search /> Filter</Button>
        </form>
        <Card class="gap-0 overflow-hidden py-0"
            ><CardContent class="p-0">
                <div v-if="classes.data.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Class</th>
                                <th class="px-5 py-3">Course</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Schedule</th>
                                <th class="px-5 py-3">Active students</th>
                                <th class="px-5 py-3 text-right">View</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="item in classes.data" :key="item.id">
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
                                    {{ item.active_students_count }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <Button
                                        size="icon-sm"
                                        variant="outline"
                                        as-child
                                        ><Link
                                            :href="show(item.id)"
                                            :aria-label="`Open ${item.name}`"
                                            ><Eye /></Link
                                    ></Button>
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
                    <p class="font-medium">No assigned classes found</p>
                </div>
            </CardContent></Card
        >
        <PaginationLinks
            :links="classes.links"
            :from="classes.from"
            :to="classes.to"
            :total="classes.total"
            item-label="classes"
        />
    </div>
</template>
