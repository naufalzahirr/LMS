<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BookOpenCheck,
    CheckCircle2,
    ClipboardCheck,
    GraduationCap,
    LibraryBig,
    NotebookText,
    ShieldCheck,
    UsersRound,
} from '@lucide/vue';
import EmptyState from '@/components/dashboard/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard as dashboardRoute } from '@/routes';
import type { AdminDashboard } from '@/types/dashboard';

const props = defineProps<{ dashboard: AdminDashboard }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboardRoute() }],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Admin dashboard"
            description="System-wide learning activity and what needs your attention."
        />

        <section aria-labelledby="overview-heading" class="space-y-4">
            <h2 id="overview-heading" class="text-lg font-semibold">
                Active learning overview
            </h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <Card>
                    <CardContent class="flex items-center gap-4">
                        <UsersRound class="size-7 text-muted-foreground" />
                        <div>
                            <p class="text-2xl font-semibold">
                                {{ props.dashboard.overview.active_classes }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Active classes
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4">
                        <BookOpenCheck class="size-7 text-muted-foreground" />
                        <div>
                            <p class="text-2xl font-semibold">
                                {{
                                    props.dashboard.overview.active_enrollments
                                }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Active students
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4">
                        <ShieldCheck class="size-7 text-muted-foreground" />
                        <div>
                            <p class="text-2xl font-semibold">
                                {{
                                    props.dashboard.overview
                                        .tutors_with_assignments
                                }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Tutors teaching
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4">
                        <NotebookText class="size-7 text-muted-foreground" />
                        <div>
                            <p class="text-2xl font-semibold">
                                {{ props.dashboard.overview.active_courses }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Active courses
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4">
                        <GraduationCap class="size-7 text-muted-foreground" />
                        <div>
                            <p class="text-2xl font-semibold">
                                {{ props.dashboard.overview.active_programs }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Active programs
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </section>

        <section aria-labelledby="attention-heading" class="space-y-4">
            <h2 id="attention-heading" class="text-lg font-semibold">
                Needs attention
            </h2>

            <Card
                v-if="
                    !props.dashboard.needs_attention.classes_without_tutor
                        .total &&
                    !props.dashboard.needs_attention.classes_without_students
                        .total
                "
            >
                <CardContent>
                    <EmptyState
                        :icon="CheckCircle2"
                        title="No administrative items need attention."
                    />
                </CardContent>
            </Card>

            <div v-else class="grid gap-4 md:grid-cols-2">
                <Card
                    v-if="
                        props.dashboard.needs_attention.classes_without_tutor
                            .total
                    "
                >
                    <CardContent class="space-y-3">
                        <div class="flex items-center gap-2 font-medium">
                            <AlertTriangle class="size-5 text-amber-600" />
                            {{
                                props.dashboard.needs_attention
                                    .classes_without_tutor.total
                            }}
                            {{
                                props.dashboard.needs_attention
                                    .classes_without_tutor.total === 1
                                    ? 'class has'
                                    : 'classes have'
                            }}
                            no tutor assigned
                        </div>
                        <ul class="space-y-1.5 text-sm">
                            <li
                                v-for="item in props.dashboard.needs_attention
                                    .classes_without_tutor.items"
                                :key="item.id"
                            >
                                <Link
                                    :href="item.url"
                                    class="text-primary hover:underline"
                                    >{{ item.name }}</Link
                                >
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card
                    v-if="
                        props.dashboard.needs_attention.classes_without_students
                            .total
                    "
                >
                    <CardContent class="space-y-3">
                        <div class="flex items-center gap-2 font-medium">
                            <AlertTriangle class="size-5 text-amber-600" />
                            {{
                                props.dashboard.needs_attention
                                    .classes_without_students.total
                            }}
                            {{
                                props.dashboard.needs_attention
                                    .classes_without_students.total === 1
                                    ? 'class has'
                                    : 'classes have'
                            }}
                            no enrolled students
                        </div>
                        <ul class="space-y-1.5 text-sm">
                            <li
                                v-for="item in props.dashboard.needs_attention
                                    .classes_without_students.items"
                                :key="item.id"
                            >
                                <Link
                                    :href="item.url"
                                    class="text-primary hover:underline"
                                    >{{ item.name }}</Link
                                >
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section aria-labelledby="content-heading" class="space-y-4">
                <h2 id="content-heading" class="text-lg font-semibold">
                    Content overview
                </h2>
                <Card>
                    <CardContent class="grid grid-cols-2 gap-4 text-sm">
                        <div class="flex items-center gap-3">
                            <GraduationCap
                                class="size-5 text-muted-foreground"
                            />
                            <div>
                                <p class="text-lg font-semibold">
                                    {{ props.dashboard.content.programs }}
                                </p>
                                <p class="text-muted-foreground">Programs</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <LibraryBig class="size-5 text-muted-foreground" />
                            <div>
                                <p class="text-lg font-semibold">
                                    {{ props.dashboard.content.courses }}
                                </p>
                                <p class="text-muted-foreground">Courses</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <NotebookText
                                class="size-5 text-muted-foreground"
                            />
                            <div>
                                <p class="text-lg font-semibold">
                                    {{ props.dashboard.content.lessons }}
                                </p>
                                <p class="text-muted-foreground">Lessons</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <ClipboardCheck
                                class="size-5 text-muted-foreground"
                            />
                            <div>
                                <p class="text-lg font-semibold">
                                    {{ props.dashboard.content.assessments }}
                                </p>
                                <p class="text-muted-foreground">Assessments</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <section
                aria-labelledby="learning-status-heading"
                class="space-y-4"
            >
                <h2 id="learning-status-heading" class="text-lg font-semibold">
                    Learning status
                </h2>
                <Card>
                    <CardContent class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <p class="text-lg font-semibold">
                                {{
                                    props.dashboard.learning_status
                                        .students_currently_learning
                                }}
                            </p>
                            <p class="text-muted-foreground">
                                Currently learning
                            </p>
                        </div>
                        <div>
                            <p class="text-lg font-semibold">
                                {{
                                    props.dashboard.learning_status
                                        .competencies_mastered
                                }}
                            </p>
                            <p class="text-muted-foreground">
                                Competencies mastered
                            </p>
                        </div>
                        <div>
                            <p class="text-lg font-semibold">
                                {{
                                    props.dashboard.learning_status
                                        .students_needing_remedial
                                }}
                            </p>
                            <p class="text-muted-foreground">Need remedial</p>
                        </div>
                    </CardContent>
                </Card>
            </section>
        </div>

        <section aria-labelledby="quick-actions-heading" class="space-y-4">
            <h2 id="quick-actions-heading" class="text-lg font-semibold">
                Quick actions
            </h2>
            <div class="flex flex-wrap gap-3">
                <Button
                    v-for="action in props.dashboard.quick_actions"
                    :key="action.label"
                    variant="outline"
                    as-child
                >
                    <Link :href="action.url">{{ action.label }}</Link>
                </Button>
            </div>
        </section>
    </div>
</template>
