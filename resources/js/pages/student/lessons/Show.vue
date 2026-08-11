<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    Circle,
    CirclePlay,
    Clock3,
    LockKeyhole,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
    RotateCcw,
    Trophy,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import LessonContentRenderer from '@/components/lesson/LessonContentRenderer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as classShow } from '@/routes/student/classes';
import { update as updateProgress } from '@/routes/student/lesson-progress';
import type { LessonProgressStatus, PlayerCompetency } from '@/types/learning';
import type { LessonDocument } from '@/types/lesson-content';

type LessonLink = { id: number; title: string; url: string };
type LessonDetails = {
    id: number;
    title: string;
    duration_minutes: number | null;
    competency: string;
    module: string;
    content_document: LessonDocument;
    progress_status: LessonProgressStatus;
};

const props = defineProps<{
    learningClass: {
        id: number;
        name: string;
        course: string;
        completed_lessons: number;
        total_lessons: number;
        progress_percentage: number;
    };
    lesson: LessonDetails;
    canMutate: boolean;
    previousLesson: LessonLink | null;
    nextLesson: LessonLink | null;
    competencies: PlayerCompetency[];
}>();

const mobileNavigationOpen = ref(false);
const navigationCollapsed = ref(false);

defineOptions({
    layout: { breadcrumbs: [] },
});

function setProgress(status: 'in_progress' | 'completed'): void {
    router.patch(
        updateProgress.url([props.learningClass.id, props.lesson.id]),
        { status },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="lesson.title" />

    <div
        v-if="mobileNavigationOpen"
        class="fixed inset-0 z-40 bg-black/45 lg:hidden"
        aria-hidden="true"
        @click="mobileNavigationOpen = false"
    />

    <div class="flex min-h-[calc(100vh-4rem)] min-w-0 flex-1">
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex w-[min(21rem,88vw)] shrink-0 flex-col border-r bg-background transition-transform duration-200 lg:sticky lg:top-0 lg:z-10 lg:h-[calc(100vh-4rem)] lg:translate-x-0',
                mobileNavigationOpen ? 'translate-x-0' : '-translate-x-full',
                navigationCollapsed ? 'lg:w-20' : 'lg:w-80',
            ]"
        >
            <div class="flex items-center justify-between border-b p-4">
                <Button
                    v-if="!navigationCollapsed"
                    variant="ghost"
                    class="-ml-2"
                    as-child
                >
                    <Link :href="classShow(learningClass.id)">
                        <ArrowLeft /> Course overview
                    </Link>
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="lg:hidden"
                    aria-label="Close lesson navigation"
                    @click="mobileNavigationOpen = false"
                >
                    <X />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="hidden lg:inline-flex"
                    :aria-label="
                        navigationCollapsed
                            ? 'Expand lesson navigation'
                            : 'Collapse lesson navigation'
                    "
                    @click="navigationCollapsed = !navigationCollapsed"
                >
                    <PanelLeftOpen v-if="navigationCollapsed" />
                    <PanelLeftClose v-else />
                </Button>
            </div>

            <div v-if="!navigationCollapsed" class="border-b p-4">
                <p class="font-semibold">{{ learningClass.course }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ learningClass.name }}
                </p>
                <div class="mt-4 flex items-center justify-between text-xs">
                    <span>Course progress</span>
                    <span class="font-medium"
                        >{{ learningClass.completed_lessons }} /
                        {{ learningClass.total_lessons }}</span
                    >
                </div>
                <div
                    class="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    :aria-valuenow="learningClass.progress_percentage"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="Course progress"
                >
                    <div
                        class="h-full rounded-full bg-primary transition-all"
                        :style="{
                            width: `${learningClass.progress_percentage}%`,
                        }"
                    />
                </div>
            </div>

            <nav
                v-if="!navigationCollapsed"
                class="flex-1 space-y-6 overflow-y-auto p-4"
                aria-label="Course lessons"
            >
                <section
                    v-for="competency in competencies"
                    :key="competency.id"
                >
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <p
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            {{ competency.name }}
                        </p>
                        <Badge
                            v-if="competency.mastery_status === 'mastered'"
                            variant="secondary"
                            class="gap-1 text-[10px]"
                        >
                            <Trophy class="size-3" /> Mastered
                        </Badge>
                    </div>
                    <div
                        v-for="module in competency.modules"
                        :key="module.id"
                        class="mb-4"
                    >
                        <p class="mb-1.5 px-2 text-sm font-medium">
                            {{ module.name }}
                        </p>
                        <component
                            v-for="item in module.lessons"
                            :key="item.id"
                            :is="item.url ? Link : 'div'"
                            :href="item.url ?? undefined"
                            class="flex items-start gap-2 rounded-lg px-2 py-2 text-sm transition-colors"
                            :class="
                                item.id === lesson.id
                                    ? 'bg-primary text-primary-foreground'
                                    : item.url
                                      ? 'hover:bg-muted'
                                      : 'cursor-not-allowed opacity-55'
                            "
                            @click="mobileNavigationOpen = false"
                        >
                            <LockKeyhole
                                v-if="!item.url"
                                class="mt-0.5 size-4 shrink-0"
                            />
                            <CheckCircle2
                                v-else-if="item.progress_status === 'completed'"
                                class="mt-0.5 size-4 shrink-0"
                                :class="
                                    item.id === lesson.id
                                        ? ''
                                        : 'text-emerald-600'
                                "
                            />
                            <CirclePlay
                                v-else-if="
                                    item.progress_status === 'in_progress'
                                "
                                class="mt-0.5 size-4 shrink-0"
                            />
                            <Circle v-else class="mt-0.5 size-4 shrink-0" />
                            <span class="leading-5">{{ item.title }}</span>
                        </component>
                    </div>
                </section>
            </nav>
        </aside>

        <main class="min-w-0 flex-1 px-4 py-5 sm:px-6 md:py-9 lg:px-10">
            <div class="mx-auto max-w-4xl">
                <div class="mb-7 flex items-center justify-between lg:hidden">
                    <Button
                        variant="outline"
                        @click="mobileNavigationOpen = true"
                    >
                        <Menu /> Lessons
                    </Button>
                    <span class="text-sm font-medium text-muted-foreground">
                        {{ learningClass.progress_percentage }}%
                    </span>
                </div>

                <header class="border-b pb-7">
                    <p class="text-sm font-medium text-muted-foreground">
                        {{ lesson.competency }} · {{ lesson.module }}
                    </p>
                    <h1
                        class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        {{ lesson.title }}
                    </h1>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <span
                            v-if="lesson.duration_minutes !== null"
                            class="flex items-center gap-1.5 text-sm text-muted-foreground"
                        >
                            <Clock3 class="size-4" />
                            {{ lesson.duration_minutes }} min read
                        </span>
                        <Badge v-if="!canMutate" variant="secondary">
                            Read only
                        </Badge>
                        <Badge
                            v-if="lesson.progress_status === 'completed'"
                            variant="outline"
                            class="gap-1 text-emerald-700 dark:text-emerald-400"
                        >
                            <CheckCircle2 class="size-3.5" /> Completed
                        </Badge>
                    </div>
                </header>

                <LessonContentRenderer
                    :document="lesson.content_document"
                    class="py-7 sm:py-10"
                />

                <footer class="border-t pt-6 pb-10">
                    <div
                        class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <Button
                            v-if="previousLesson"
                            variant="outline"
                            as-child
                        >
                            <Link :href="previousLesson.url">
                                <ArrowLeft /> {{ previousLesson.title }}
                            </Link>
                        </Button>
                        <span v-else />

                        <div class="flex flex-wrap justify-end gap-2">
                            <Button
                                v-if="
                                    canMutate &&
                                    lesson.progress_status === 'completed'
                                "
                                variant="outline"
                                @click="setProgress('in_progress')"
                            >
                                <RotateCcw /> Reopen lesson
                            </Button>
                            <Button
                                v-else-if="canMutate"
                                @click="setProgress('completed')"
                            >
                                <CheckCircle2 /> Mark complete
                            </Button>
                            <Button
                                v-if="nextLesson"
                                variant="outline"
                                as-child
                            >
                                <Link :href="nextLesson.url">
                                    {{ nextLesson.title }} <ArrowRight />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </footer>
            </div>
        </main>
    </div>
</template>
