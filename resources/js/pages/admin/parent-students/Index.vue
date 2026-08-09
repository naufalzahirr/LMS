<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, Trash2, UsersRound } from '@lucide/vue';
import { reactive } from 'vue';
import ParentStudentRelationshipController from '@/actions/App/Http/Controllers/Admin/ParentStudentRelationshipController';
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
import { create, index } from '@/routes/admin/parent-students';
import type {
    DeliveryPaginator,
    ParentRelationshipType,
    SelectOption,
} from '@/types/delivery';

type RelationshipRow = {
    id: number;
    parent: { name: string; email: string };
    student: { name: string; email: string };
    relationship_type: ParentRelationshipType;
    relationship_label: string;
};
const props = defineProps<{
    relationships: DeliveryPaginator<RelationshipRow>;
    filters: { search: string; relationship_type: string };
    relationshipTypes: SelectOption<ParentRelationshipType>[];
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Parent–students', href: index() }] },
});
const filters = reactive({
    search: props.filters.search,
    relationship_type: props.filters.relationship_type || 'all',
});
function applyFilters(): void {
    router.get(
        index.url(),
        {
            search: filters.search || undefined,
            relationship_type:
                filters.relationship_type === 'all'
                    ? undefined
                    : filters.relationship_type,
        },
        { preserveState: true, replace: true },
    );
}
function removeRelationship(item: RelationshipRow): void {
    if (
        !window.confirm(
            `Remove the link between ${item.parent.name} and ${item.student.name}?`,
        )
    ) {
        return;
    }

    router.delete(ParentStudentRelationshipController.destroy.url(item.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Parent–student relationships" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Parent–student relationships"
                description="Manage which Parent account can represent each Student."
            /><Button as-child
                ><Link :href="create()"><Plus /> Add relationship</Link></Button
            >
        </div>
        <form
            class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-[1fr_14rem_auto]"
            @submit.prevent="applyFilters"
        >
            <Input
                v-model="filters.search"
                placeholder="Search parent or student"
            /><Select v-model="filters.relationship_type"
                ><SelectTrigger class="w-full"><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectItem value="all">All relationships</SelectItem
                    ><SelectItem
                        v-for="type in relationshipTypes"
                        :key="type.value"
                        :value="type.value"
                        >{{ type.label }}</SelectItem
                    ></SelectContent
                ></Select
            ><Button type="submit"><Search /> Filter</Button>
        </form>
        <Card class="gap-0 overflow-hidden py-0"
            ><CardContent class="p-0">
                <div v-if="relationships.data.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left">
                            <tr>
                                <th class="px-5 py-3">Parent</th>
                                <th class="px-5 py-3">Student</th>
                                <th class="px-5 py-3">Relationship</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="item in relationships.data"
                                :key="item.id"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium">
                                        {{ item.parent.name }}
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{ item.parent.email }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium">
                                        {{ item.student.name }}
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{ item.student.email }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <Badge variant="outline">{{
                                        item.relationship_label
                                    }}</Badge>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <Button
                                        size="icon-sm"
                                        variant="destructive"
                                        :aria-label="`Remove relationship for ${item.student.name}`"
                                        @click="removeRelationship(item)"
                                        ><Trash2
                                    /></Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-else
                    class="flex flex-col items-center px-6 py-16 text-center"
                >
                    <UsersRound class="mb-4 size-10 text-muted-foreground" />
                    <p class="font-medium">No relationships found</p>
                </div>
            </CardContent></Card
        >
        <PaginationLinks
            :links="relationships.links"
            :from="relationships.from"
            :to="relationships.to"
            :total="relationships.total"
            item-label="relationships"
        />
    </div>
</template>
