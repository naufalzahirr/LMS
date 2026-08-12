<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    ClipboardCheck,
    UsersRound,
} from '@lucide/vue';
import EmptyState from '@/components/dashboard/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard as dashboardRoute } from '@/routes';
import { index as tutorClassesIndex } from '@/routes/tutor/classes';
import type { TutorDashboard } from '@/types/dashboard';

const props = defineProps<{ dashboard: TutorDashboard }>();

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
            title="Teaching dashboard"
            description="Your classes, grading work, and students who need attention."
        />

        <section aria-labelledby="grading-heading" class="space-y-4">
            <h2 id="grading-heading" class="text-lg font-semibold">
                Grading queue
            </h2>
            <Card
                :class="
                    props.dashboard.grading_queue.count
                        ? 'border-amber-300 dark:border-amber-800'
                        : ''
                "
            >
                <CardContent
                    v-if="props.dashboard.grading_queue.count"
                    class="space-y-4"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <p class="text-base font-medium">
                            {{ props.dashboard.grading_queue.count }}
                            {{
                                props.dashboard.grading_queue.count === 1
                                    ? 'submission needs'
                                    : 'submissions need'
                            }}
                            review
                        </p>
                        <ClipboardCheck class="size-6 text-amber-600" />
                    </div>
                    <div class="grid gap-2">
                        <div
                            v-for="item in props.dashboard.grading_queue.items"
                            :key="item.assignment_id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-background p-3"
                        >
                            <div>
                                <p class="font-medium">{{ item.title }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ item.class_name }} · {{ item.count }}
                                    pending
                                </p>
                            </div>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="item.review_url"
                                    >Review grading <ArrowRight
                                /></Link>
                            </Button>
                        </div>
                    </div>
                </CardContent>
                <CardContent v-else>
                    <EmptyState
                        :icon="CheckCircle2"
                        title="No submissions waiting for grading."
                    />
                </CardContent>
            </Card>
        </section>

        <section aria-labelledby="attention-heading" class="space-y-4">
            <h2 id="attention-heading" class="text-lg font-semibold">
                Needs attention
            </h2>
            <Card>
                <CardContent
                    v-if="props.dashboard.needs_attention.needs_remedial_count"
                    class="flex flex-wrap items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-3">
                        <AlertTriangle class="size-6 text-amber-600" />
                        <p class="font-medium">
                            {{
                                props.dashboard.needs_attention
                                    .needs_remedial_count
                            }}
                            {{
                                props.dashboard.needs_attention
                                    .needs_remedial_count === 1
                                    ? 'student needs'
                                    : 'students need'
                            }}
                            remedial support
                        </p>
                    </div>
                    <Button variant="outline" as-child>
                        <Link
                            :href="
                                props.dashboard.needs_attention
                                    .needs_remedial_url
                            "
                            >View details <ArrowRight
                        /></Link>
                    </Button>
                </CardContent>
                <CardContent v-else>
                    <EmptyState
                        :icon="CheckCircle2"
                        title="No students currently need remedial support."
                    />
                </CardContent>
            </Card>
        </section>

        <section aria-labelledby="classes-heading" class="space-y-4">
            <h2 id="classes-heading" class="text-lg font-semibold">
                My classes
            </h2>

            <Card v-if="!props.dashboard.my_classes.length">
                <CardContent>
                    <EmptyState
                        :icon="UsersRound"
                        title="You have no assigned classes yet."
                        description="An administrator needs to assign you to a class before it appears here."
                    />
                </CardContent>
            </Card>

            <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card v-for="item in props.dashboard.my_classes" :key="item.id">
                    <CardHeader>
                        <CardTitle class="break-words">{{
                            item.name
                        }}</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            {{ item.program }} · {{ item.course }}
                        </p>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <Badge variant="secondary" class="w-fit gap-1">
                            <UsersRound class="size-3.5" />
                            {{ item.active_students_count }}
                            {{
                                item.active_students_count === 1
                                    ? 'student'
                                    : 'students'
                            }}
                        </Badge>
                        <Button as-child>
                            <Link :href="item.url">Open class</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <p v-if="props.dashboard.my_classes.length" class="text-sm">
                <Link
                    :href="tutorClassesIndex()"
                    class="text-primary hover:underline"
                    >View all my classes <ArrowRight class="inline size-3.5"
                /></Link>
            </p>
        </section>

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
