<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { GraduationCap, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, reactive } from 'vue';
import ProgramController from '@/actions/App/Http/Controllers/Admin/ProgramController';
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
import { create, index } from '@/routes/admin/programs';
import type {
    AcademicStatus,
    AcademicStatusOption,
    Paginated,
} from '@/types/academic';

type ProgramRow = {
    id: number;
    name: string;
    code: string | null;
    courses_count: number;
    status: AcademicStatus;
};

const props = defineProps<{
    programs: Paginated<ProgramRow>;
    filters: { search: string; status: string };
    statuses: AcademicStatusOption[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Programs', href: index() }],
    },
});

const page = usePage();
const filters = reactive({
    search: props.filters.search,
    status: props.filters.status || 'all',
});
const deletionErrors = computed(() => {
    const error = page.props.errors?.program;

    return typeof error === 'string' ? [error] : [];
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

function resetFilters(): void {
    filters.search = '';
    filters.status = 'all';
    applyFilters();
}

function removeProgram(program: ProgramRow): void {
    if (!window.confirm(`Delete ${program.name}?`)) {
        return;
    }

    router.delete(ProgramController.destroy.url(program.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Programs" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Programs"
                description="Manage the top-level academic structure."
            />
            <Button v-if="canManage" as-child>
                <Link :href="create()"><Plus /> Add program</Link>
            </Button>
        </div>

        <AlertError
            v-if="deletionErrors.length"
            :errors="deletionErrors"
            title="Program could not be deleted."
        />

        <form
            class="grid gap-3 rounded-lg border bg-card p-4 sm:grid-cols-[minmax(0,1fr)_12rem_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <Input
                v-model="filters.search"
                aria-label="Search programs"
                placeholder="Search name or code"
            />
            <Select v-model="filters.status">
                <SelectTrigger aria-label="Filter by status" class="w-full">
                    <SelectValue placeholder="All statuses" />
                </SelectTrigger>
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
            <Button type="button" variant="outline" @click="resetFilters">
                Reset
            </Button>
        </form>

        <Card class="gap-0 overflow-hidden py-0">
            <CardContent class="p-0">
                <div v-if="programs.data.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-6 py-3 font-medium">Name</th>
                                <th class="px-6 py-3 font-medium">Code</th>
                                <th class="px-6 py-3 font-medium">Courses</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th
                                    v-if="canManage"
                                    class="px-6 py-3 text-right font-medium"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="program in programs.data"
                                :key="program.id"
                                class="transition-colors hover:bg-muted/30"
                            >
                                <td class="px-6 py-4 font-medium">
                                    {{ program.name }}
                                </td>
                                <td class="px-6 py-4 text-muted-foreground">
                                    {{ program.code ?? '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    {{ program.courses_count }}
                                </td>
                                <td class="px-6 py-4">
                                    <Badge
                                        :variant="
                                            program.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{ program.status }}
                                    </Badge>
                                </td>
                                <td v-if="canManage" class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="icon-sm"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    ProgramController.edit(
                                                        program.id,
                                                    )
                                                "
                                                :aria-label="`Edit ${program.name}`"
                                            >
                                                <Pencil />
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="icon-sm"
                                            :aria-label="`Delete ${program.name}`"
                                            @click="removeProgram(program)"
                                        >
                                            <Trash2 />
                                        </Button>
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
                    <p class="font-medium">No programs found</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Adjust the filters or create the first program.
                    </p>
                </div>
            </CardContent>
        </Card>

        <PaginationLinks
            :links="programs.links"
            :from="programs.from"
            :to="programs.to"
            :total="programs.total"
            item-label="programs"
        />
    </div>
</template>
