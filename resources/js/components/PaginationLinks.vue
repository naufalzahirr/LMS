<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { PaginationLink } from '@/types/academic';

defineProps<{
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
    itemLabel: string;
}>();

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Previous')
        .replace('Next &raquo;', 'Next');
}
</script>

<template>
    <div
        v-if="total > 0"
        class="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between"
    >
        <p class="text-muted-foreground">
            Showing {{ from }}–{{ to }} of {{ total }} {{ itemLabel }}
        </p>
        <nav
            class="flex flex-wrap gap-1"
            :aria-label="`${itemLabel} pagination`"
        >
            <Button
                v-for="link in links"
                :key="link.label"
                :variant="link.active ? 'default' : 'outline'"
                size="sm"
                :disabled="!link.url"
                as-child
            >
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ paginationLabel(link.label) }}
                </Link>
                <span v-else>{{ paginationLabel(link.label) }}</span>
            </Button>
        </nav>
    </div>
</template>
