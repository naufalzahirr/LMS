<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    Circle,
    CirclePlay,
    Clock3,
    Download,
    ExternalLink,
    LockKeyhole,
    RotateCcw,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { show as classShow } from '@/routes/student/classes';
import { update as updateProgress } from '@/routes/student/lesson-progress';
import type { LessonProgressStatus, PlayerCompetency } from '@/types/learning';

type LessonLink = { id: number; title: string; url: string };
type LessonDetails = {
    id: number;
    title: string;
    lesson_type: string;
    content: string | null;
    external_url: string | null;
    embed_url: string | null;
    duration_minutes: number | null;
    competency: string;
    module: string;
    file_url: string | null;
    file_download_url: string | null;
    progress_status: LessonProgressStatus;
};

const props = defineProps<{
    learningClass: { id: number; name: string; course: string };
    lesson: LessonDetails;
    canMutate: boolean;
    previousLesson: LessonLink | null;
    nextLesson: LessonLink | null;
    competencies: PlayerCompetency[];
}>();

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
        class="grid min-h-[calc(100vh-5rem)] flex-1 lg:grid-cols-[19rem_minmax(0,1fr)]"
    >
        <aside
            class="border-b bg-muted/20 p-4 lg:border-r lg:border-b-0 lg:p-5"
        >
            <Button variant="ghost" class="mb-5 -ml-2" as-child>
                <Link :href="classShow(learningClass.id)">
                    <ArrowLeft /> Course overview
                </Link>
            </Button>
            <p class="mb-4 text-sm font-semibold">{{ learningClass.course }}</p>
            <nav class="space-y-5">
                <div v-for="competency in competencies" :key="competency.id">
                    <p
                        class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ competency.name }}
                    </p>
                    <div
                        v-for="module in competency.modules"
                        :key="module.id"
                        class="mb-3"
                    >
                        <p class="mb-1 px-2 text-sm font-medium">
                            {{ module.name }}
                        </p>
                        <component
                            v-for="item in module.lessons"
                            :key="item.id"
                            :is="item.url ? Link : 'div'"
                            :href="item.url ?? undefined"
                            class="flex items-center gap-2 rounded-md px-2 py-2 text-sm"
                            :class="
                                item.id === lesson.id
                                    ? 'bg-muted font-medium'
                                    : item.url
                                      ? 'hover:bg-muted'
                                      : 'cursor-not-allowed opacity-60'
                            "
                        >
                            <LockKeyhole
                                v-if="!item.url"
                                class="size-4 shrink-0"
                            />
                            <CheckCircle2
                                v-else-if="item.progress_status === 'completed'"
                                class="size-4 shrink-0 text-emerald-600"
                            />
                            <CirclePlay
                                v-else-if="
                                    item.progress_status === 'in_progress'
                                "
                                class="size-4 shrink-0 text-primary"
                            />
                            <Circle
                                v-else
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <span>{{ item.title }}</span>
                        </component>
                    </div>
                </div>
            </nav>
        </aside>

        <main class="min-w-0 p-4 md:p-8 lg:p-10">
            <div class="mx-auto max-w-4xl space-y-7">
                <header>
                    <p class="text-sm font-medium text-muted-foreground">
                        {{ lesson.competency }} · {{ lesson.module }}
                    </p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                        {{ lesson.title }}
                    </h1>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <Badge variant="outline">{{
                            lesson.lesson_type
                        }}</Badge>
                        <span
                            v-if="lesson.duration_minutes !== null"
                            class="flex items-center gap-1 text-sm text-muted-foreground"
                        >
                            <Clock3 class="size-4" />
                            {{ lesson.duration_minutes }} min
                        </span>
                        <Badge v-if="!canMutate" variant="secondary"
                            >Read only</Badge
                        >
                    </div>
                </header>

                <Card>
                    <CardContent class="space-y-6 py-6">
                        <p
                            v-if="lesson.content"
                            class="text-base leading-7 whitespace-pre-wrap"
                        >
                            {{ lesson.content }}
                        </p>

                        <div
                            v-if="
                                lesson.lesson_type === 'video' &&
                                lesson.embed_url
                            "
                            class="aspect-video overflow-hidden rounded-lg bg-black"
                        >
                            <iframe
                                :src="lesson.embed_url"
                                class="size-full"
                                title="Lesson video"
                                loading="lazy"
                                allow="
                                    accelerometer;
                                    autoplay;
                                    clipboard-write;
                                    encrypted-media;
                                    gyroscope;
                                    picture-in-picture;
                                "
                                allowfullscreen
                            ></iframe>
                        </div>

                        <Button
                            v-else-if="
                                lesson.lesson_type === 'video' &&
                                lesson.external_url
                            "
                            as-child
                        >
                            <a
                                :href="lesson.external_url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Open video <ExternalLink />
                            </a>
                        </Button>

                        <Button
                            v-if="
                                lesson.lesson_type === 'link' &&
                                lesson.external_url
                            "
                            as-child
                        >
                            <a
                                :href="lesson.external_url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Open learning resource <ExternalLink />
                            </a>
                        </Button>

                        <div
                            v-if="
                                lesson.lesson_type === 'document' &&
                                lesson.file_url
                            "
                            class="flex flex-wrap gap-3"
                        >
                            <Button as-child>
                                <a
                                    :href="lesson.file_url"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    View PDF <ExternalLink />
                                </a>
                            </Button>
                            <Button variant="outline" as-child>
                                <a
                                    :href="
                                        lesson.file_download_url ??
                                        lesson.file_url
                                    "
                                >
                                    Download PDF <Download />
                                </a>
                            </Button>
                        </div>

                        <img
                            v-if="
                                lesson.lesson_type === 'image' &&
                                lesson.file_url
                            "
                            :src="lesson.file_url"
                            :alt="lesson.title"
                            class="max-h-[42rem] max-w-full rounded-lg border object-contain"
                        />
                    </CardContent>
                </Card>

                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t pt-6"
                >
                    <Button v-if="previousLesson" variant="outline" as-child>
                        <Link :href="previousLesson.url">
                            <ArrowLeft /> {{ previousLesson.title }}
                        </Link>
                    </Button>
                    <span v-else></span>

                    <div class="flex flex-wrap gap-2">
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
                            <CheckCircle2 /> Mark as complete
                        </Button>
                        <Button v-if="nextLesson" variant="outline" as-child>
                            <Link :href="nextLesson.url">
                                {{ nextLesson.title }} <ArrowRight />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
