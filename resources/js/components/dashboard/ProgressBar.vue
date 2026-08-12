<script setup lang="ts">
const props = defineProps<{
    percentage: number;
    label?: string;
}>();
</script>

<template>
    <div class="space-y-1.5">
        <div v-if="label" class="flex items-center justify-between text-sm">
            <span class="text-muted-foreground">{{ label }}</span>
            <span class="font-medium">{{ props.percentage }}%</span>
        </div>
        <div
            class="h-2 overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="props.percentage"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="label ?? `${props.percentage}% complete`"
        >
            <div
                class="h-full rounded-full bg-primary transition-all"
                :style="{ width: `${props.percentage}%` }"
            ></div>
        </div>
    </div>
</template>
