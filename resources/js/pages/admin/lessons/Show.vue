<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from '@lucide/vue';
import LessonController from '@/actions/App/Http/Controllers/Admin/LessonController';
import Heading from '@/components/Heading.vue';
import LessonContentRenderer from '@/components/lesson/LessonContentRenderer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/admin/lessons';
import type { AcademicStatus, LessonType } from '@/types/academic';
import type { LessonDocument } from '@/types/lesson-content';

type PreviewLesson = {
    id: number;
    title: string;
    lesson_type: LessonType;
    content: string | null;
    external_url: string | null;
    duration_minutes: number | null;
    status: AcademicStatus;
    module: string;
    competency: string;
    course: string;
    program: string;
    file_url: string | null;
    file_download_url: string | null;
    content_document: LessonDocument;
};

defineProps<{ lesson: PreviewLesson; canManage: boolean }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lessons', href: index() },
            { title: 'Preview', href: index() },
        ],
    },
});
</script>

<template>
    <Head :title="lesson.title" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="lesson.title"
                description="Admin and tutor content preview."
            />
            <div class="flex gap-2">
                <Button variant="outline" as-child
                    ><Link :href="index()"><ArrowLeft /> Lessons</Link></Button
                >
                <Button v-if="canManage" as-child
                    ><Link :href="LessonController.edit(lesson.id)"
                        ><Pencil /> Edit</Link
                    ></Button
                >
            </div>
        </div>

        <Card>
            <CardContent class="grid gap-4 pt-2 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs font-medium text-muted-foreground">
                        Program / Course
                    </p>
                    <p class="mt-1 text-sm">
                        {{ lesson.program }} / {{ lesson.course }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium text-muted-foreground">
                        Competency
                    </p>
                    <p class="mt-1 text-sm">{{ lesson.competency }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-muted-foreground">
                        Module
                    </p>
                    <p class="mt-1 text-sm">{{ lesson.module }}</p>
                </div>
                <div class="flex items-start gap-2">
                    <Badge variant="outline">Multimedia lesson</Badge
                    ><Badge
                        :variant="
                            lesson.status === 'active' ? 'default' : 'secondary'
                        "
                        >{{ lesson.status }}</Badge
                    ><span
                        v-if="lesson.duration_minutes !== null"
                        class="text-sm text-muted-foreground"
                        >{{ lesson.duration_minutes }} min</span
                    >
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Content preview</CardTitle></CardHeader>
            <CardContent>
                <div class="mx-auto max-w-4xl">
                    <LessonContentRenderer
                        :document="lesson.content_document"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
