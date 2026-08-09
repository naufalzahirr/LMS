<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, ExternalLink, Pencil } from '@lucide/vue';
import LessonController from '@/actions/App/Http/Controllers/Admin/LessonController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/admin/lessons';
import type { AcademicStatus, LessonType } from '@/types/academic';

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
                    <Badge variant="outline">{{ lesson.lesson_type }}</Badge
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
            <CardContent class="space-y-5">
                <div
                    v-if="lesson.lesson_type === 'text'"
                    class="leading-7 whitespace-pre-wrap"
                >
                    {{ lesson.content }}
                </div>
                <div
                    v-else-if="lesson.lesson_type === 'video'"
                    class="space-y-4"
                >
                    <p class="text-sm text-muted-foreground">
                        Open the validated external video in a new tab.
                    </p>
                    <Button as-child
                        ><a
                            :href="lesson.external_url ?? '#'"
                            target="_blank"
                            rel="noopener noreferrer"
                            ><ExternalLink /> Open video</a
                        ></Button
                    >
                </div>
                <div
                    v-else-if="lesson.lesson_type === 'link'"
                    class="space-y-4"
                >
                    <p class="text-sm text-muted-foreground">
                        This lesson points to an external learning resource.
                    </p>
                    <Button as-child
                        ><a
                            :href="lesson.external_url ?? '#'"
                            target="_blank"
                            rel="noopener noreferrer"
                            ><ExternalLink /> Open resource</a
                        ></Button
                    >
                </div>
                <div
                    v-else-if="lesson.lesson_type === 'document'"
                    class="flex flex-wrap gap-3"
                >
                    <Button v-if="lesson.file_url" as-child
                        ><a :href="lesson.file_url" target="_blank"
                            ><ExternalLink /> View document</a
                        ></Button
                    >
                    <Button
                        v-if="lesson.file_download_url"
                        variant="outline"
                        as-child
                        ><a :href="lesson.file_download_url"
                            ><Download /> Download document</a
                        ></Button
                    >
                </div>
                <img
                    v-else-if="
                        lesson.lesson_type === 'image' && lesson.file_url
                    "
                    :src="lesson.file_url"
                    :alt="lesson.title"
                    class="max-h-[36rem] rounded-lg border object-contain"
                />

                <div
                    v-if="lesson.lesson_type !== 'text' && lesson.content"
                    class="border-t pt-5"
                >
                    <p class="mb-2 text-sm font-medium">Teacher notes</p>
                    <p
                        class="text-sm leading-6 whitespace-pre-wrap text-muted-foreground"
                    >
                        {{ lesson.content }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
