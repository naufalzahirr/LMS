<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineProps<{
    icon: LucideIcon;
    title: string;
    description?: string;
    ctaLabel?: string;
    ctaHref?: string;
}>();
</script>

<template>
    <div class="flex flex-col items-center gap-3 py-10 text-center">
        <component :is="icon" class="size-8 text-muted-foreground" />
        <div>
            <p class="font-medium">{{ title }}</p>
            <p v-if="description" class="mt-1 text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <Button
            v-if="ctaLabel && ctaHref"
            variant="outline"
            as-child
            class="mt-1"
        >
            <Link :href="ctaHref">{{ ctaLabel }}</Link>
        </Button>
    </div>
</template>
