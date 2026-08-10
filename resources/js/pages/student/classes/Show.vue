<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Circle,
    CirclePlay,
    ClipboardList,
    Clock3,
} from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/student/classes';
import type { StudentAssessmentCard } from '@/types/assessment-attempt';
import type { PlayerCompetency, StudentClassDetails } from '@/types/learning';

defineProps<{
    learningClass: StudentClassDetails;
    enrollment: { id: number; status: string; read_only: boolean };
    progress: {
        completed_lessons: number;
        total_lessons: number;
        percentage: number;
        continue_lesson_id: number | null;
    };
    competencies: PlayerCompetency[];
    assessments: StudentAssessmentCard[];
    assessments_url: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'My Learning', href: index() }] },
});
</script>

<template>
    <Head :title="learningClass.name" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="learningClass.name"
                :description="`${learningClass.program} / ${learningClass.course}`"
            />
            <Button variant="outline" as-child>
                <Link :href="index()"><ArrowLeft /> My Learning</Link>
            </Button>
        </div>

        <Card>
            <CardContent
                class="grid gap-5 md:grid-cols-[1fr_auto] md:items-center"
            >
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">Lesson completion</span>
                        <Badge v-if="enrollment.read_only" variant="secondary">
                            Read only
                        </Badge>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-primary"
                            :style="{ width: `${progress.percentage}%` }"
                        ></div>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ progress.completed_lessons }} /
                        {{ progress.total_lessons }}
                        lessons completed
                    </p>
                </div>
                <p class="text-3xl font-semibold">{{ progress.percentage }}%</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex-row items-center justify-between">
                <div>
                    <CardTitle>Assessments</CardTitle>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ assessments.length }} assigned to this class
                    </p>
                </div>
                <Button variant="outline" as-child>
                    <Link :href="assessments_url">
                        <ClipboardList /> View assessments
                    </Link>
                </Button>
            </CardHeader>
        </Card>

        <div v-if="competencies.length" class="space-y-6">
            <Card v-for="competency in competencies" :key="competency.id">
                <CardHeader>
                    <CardTitle>{{ competency.name }}</CardTitle>
                    <p
                        v-if="competency.description"
                        class="text-sm text-muted-foreground"
                    >
                        {{ competency.description }}
                    </p>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div
                        v-for="module in competency.modules"
                        :key="module.id"
                        class="space-y-2"
                    >
                        <h3 class="font-medium">{{ module.name }}</h3>
                        <div class="overflow-hidden rounded-lg border">
                            <Link
                                v-for="lesson in module.lessons"
                                :key="lesson.id"
                                :href="lesson.url"
                                class="flex items-center gap-3 border-b px-4 py-3 text-sm transition-colors last:border-b-0 hover:bg-muted/50"
                            >
                                <CheckCircle2
                                    v-if="
                                        lesson.progress_status === 'completed'
                                    "
                                    class="size-5 shrink-0 text-emerald-600"
                                />
                                <CirclePlay
                                    v-else-if="
                                        lesson.progress_status === 'in_progress'
                                    "
                                    class="size-5 shrink-0 text-primary"
                                />
                                <Circle
                                    v-else
                                    class="size-5 shrink-0 text-muted-foreground"
                                />
                                <span class="min-w-0 flex-1 font-medium">
                                    {{ lesson.title }}
                                </span>
                                <Badge variant="outline">{{
                                    lesson.lesson_type
                                }}</Badge>
                                <span
                                    v-if="lesson.duration_minutes !== null"
                                    class="hidden items-center gap-1 text-muted-foreground sm:flex"
                                >
                                    <Clock3 class="size-3.5" />
                                    {{ lesson.duration_minutes }} min
                                </span>
                            </Link>
                            <p
                                v-if="!module.lessons.length"
                                class="px-4 py-3 text-sm text-muted-foreground"
                            >
                                No active lessons in this module.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
        <Card v-else>
            <CardContent
                class="py-12 text-center text-sm text-muted-foreground"
            >
                This course does not have active learning content yet.
            </CardContent>
        </Card>
    </div>
</template>
