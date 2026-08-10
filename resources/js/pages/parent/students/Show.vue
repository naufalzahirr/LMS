<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import ChildClassProgress from '@/components/parent/ChildClassProgress.vue';
import ChildSummaryCards from '@/components/parent/ChildSummaryCards.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { ParentChildProgress } from '@/types/parent-progress';

defineProps<{ student: ParentChildProgress }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Parent Dashboard', href: '/parent/dashboard' }],
    },
});
</script>

<template>
    <Head :title="`${student.name} progress`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="student.name"
                :description="`Learning progress · ${student.email}`"
            />
            <Button variant="outline" as-child>
                <Link href="/parent/dashboard"
                    ><ArrowLeft /> Parent dashboard</Link
                >
            </Button>
        </div>

        <ChildSummaryCards :summary="student.summary" />

        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold">Current learning</h2>
                <p class="text-sm text-muted-foreground">
                    Active enrollments in active classes.
                </p>
            </div>
            <ChildClassProgress
                v-for="learningClass in student.current_classes"
                :key="`current-${learningClass.id}`"
                :learning-class="learningClass"
            />
            <Card v-if="!student.current_classes.length">
                <CardContent
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    No current classes.
                </CardContent>
            </Card>
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold">Learning history</h2>
                <p class="text-sm text-muted-foreground">
                    Completed, withdrawn, inactive, and archived class records
                    remain visible here.
                </p>
            </div>
            <ChildClassProgress
                v-for="learningClass in student.history_classes"
                :key="`history-${learningClass.id}-${learningClass.enrollment_status}`"
                :learning-class="learningClass"
            />
            <Card v-if="!student.history_classes.length">
                <CardContent
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    No historical classes.
                </CardContent>
            </Card>
        </section>
    </div>
</template>
