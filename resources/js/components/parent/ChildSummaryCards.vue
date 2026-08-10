<script setup lang="ts">
import {
    AlertTriangle,
    BookOpenCheck,
    CheckCircle2,
    School,
} from '@lucide/vue';
import { Card, CardContent } from '@/components/ui/card';
import type { ParentChildSummary } from '@/types/parent-progress';

defineProps<{ summary: ParentChildSummary }>();
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Card>
            <CardContent class="flex items-center gap-4">
                <School class="size-8 text-muted-foreground" />
                <div>
                    <p class="text-2xl font-semibold">
                        {{ summary.active_classes }}
                    </p>
                    <p class="text-sm text-muted-foreground">Active classes</p>
                </div>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="flex items-center gap-4">
                <BookOpenCheck class="size-8 text-muted-foreground" />
                <div>
                    <p class="text-2xl font-semibold">
                        {{ summary.lesson_percentage }}%
                    </p>
                    <p class="text-sm text-muted-foreground">Lesson progress</p>
                </div>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="flex items-center gap-4">
                <CheckCircle2 class="size-8 text-emerald-600" />
                <div>
                    <p class="text-2xl font-semibold">
                        {{ summary.competencies_mastered }} /
                        {{ summary.competencies_total }}
                    </p>
                    <p class="text-sm text-muted-foreground">Mastered</p>
                </div>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="flex items-center gap-4">
                <AlertTriangle class="size-8 text-amber-600" />
                <div>
                    <p class="text-2xl font-semibold">
                        {{ summary.needs_remedial }}
                    </p>
                    <p class="text-sm text-muted-foreground">Needs attention</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
