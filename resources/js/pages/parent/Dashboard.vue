<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, UsersRound } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import ChildClassProgress from '@/components/parent/ChildClassProgress.vue';
import ChildSummaryCards from '@/components/parent/ChildSummaryCards.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ParentChildProgress } from '@/types/parent-progress';

const props = defineProps<{ children: ParentChildProgress[] }>();

const onlyChild = props.children.length === 1 ? props.children[0] : null;

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Parent Dashboard', href: '/parent/dashboard' }],
    },
});
</script>

<template>
    <Head title="Parent Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Learning progress"
            description="A read-only view of your children's current and past learning progress."
        />

        <Card v-if="!children.length">
            <CardContent
                class="flex flex-col items-center gap-3 py-14 text-center"
            >
                <UsersRound class="size-10 text-muted-foreground" />
                <div>
                    <p class="font-medium">No linked students yet</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Ask an administrator to connect your parent account to a
                        student.
                    </p>
                </div>
            </CardContent>
        </Card>

        <template v-else-if="onlyChild">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold">{{ onlyChild.name }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ onlyChild.email }}
                    </p>
                </div>
                <Button variant="outline" as-child>
                    <Link :href="onlyChild.url"
                        >View full progress <ArrowRight
                    /></Link>
                </Button>
            </div>

            <ChildSummaryCards :summary="onlyChild.summary" />

            <section class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">Current learning</h2>
                    <p class="text-sm text-muted-foreground">
                        Active enrollments in active classes.
                    </p>
                </div>
                <ChildClassProgress
                    v-for="learningClass in onlyChild.current_classes"
                    :key="`current-${learningClass.id}`"
                    :learning-class="learningClass"
                />
                <Card v-if="!onlyChild.current_classes.length">
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
                        Completed, withdrawn, inactive, and archived class
                        records.
                    </p>
                </div>
                <ChildClassProgress
                    v-for="learningClass in onlyChild.history_classes"
                    :key="`history-${learningClass.id}-${learningClass.enrollment_status}`"
                    :learning-class="learningClass"
                />
                <Card v-if="!onlyChild.history_classes.length">
                    <CardContent
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No historical classes.
                    </CardContent>
                </Card>
            </section>
        </template>

        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Card v-for="child in children" :key="child.id">
                <CardHeader>
                    <CardTitle>{{ child.name }}</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        {{ child.email }}
                    </p>
                </CardHeader>
                <CardContent class="space-y-4">
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-muted-foreground">
                                Active classes
                            </dt>
                            <dd class="text-lg font-semibold">
                                {{ child.summary.active_classes }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Lessons</dt>
                            <dd class="text-lg font-semibold">
                                {{ child.summary.lesson_percentage }}%
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Mastered</dt>
                            <dd class="text-lg font-semibold">
                                {{ child.summary.competencies_mastered }} /
                                {{ child.summary.competencies_total }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Needs attention
                            </dt>
                            <dd class="text-lg font-semibold">
                                {{ child.summary.needs_remedial }}
                            </dd>
                        </div>
                    </dl>
                    <Button class="w-full" variant="outline" as-child>
                        <Link :href="child.url"
                            >View progress <ArrowRight
                        /></Link>
                    </Button>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
