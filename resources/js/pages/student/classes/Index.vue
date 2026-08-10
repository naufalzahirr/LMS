<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpenCheck, History } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/student/classes';
import type { StudentClassCard } from '@/types/learning';

defineProps<{
    currentClasses: StudentClassCard[];
    historyClasses: StudentClassCard[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'My Learning', href: index() }] },
});
</script>

<template>
    <Head title="My Learning" />

    <div class="flex h-full flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="My Learning"
            description="Continue your current classes or revisit completed learning."
        />

        <section class="space-y-4">
            <div class="flex items-center gap-2">
                <BookOpenCheck class="size-5 text-primary" />
                <h2 class="text-sm font-semibold tracking-wide uppercase">
                    Current learning
                </h2>
            </div>

            <div v-if="currentClasses.length" class="grid gap-5 lg:grid-cols-2">
                <Card v-for="item in currentClasses" :key="item.id">
                    <CardHeader>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <CardTitle>{{ item.name }}</CardTitle>
                                <p class="mt-2 text-sm text-muted-foreground">
                                    {{ item.program }} · {{ item.course }}
                                </p>
                            </div>
                            <Badge>Active</Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>Lesson completion</span>
                                <span class="font-semibold"
                                    >{{ item.percentage }}%</span
                                >
                            </div>
                            <div
                                class="h-2.5 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-primary transition-all"
                                    :style="{ width: `${item.percentage}%` }"
                                ></div>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ item.completed_lessons }} /
                                {{ item.total_lessons }}
                                lessons completed
                            </p>
                        </div>

                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                Tutor{{ item.tutors.length === 1 ? '' : 's' }}:
                                {{ item.tutors.join(', ') || 'Not assigned' }}
                            </p>
                            <Button as-child>
                                <Link :href="item.continue_url">
                                    Continue learning <ArrowRight />
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
            <Card v-else>
                <CardContent
                    class="py-10 text-center text-sm text-muted-foreground"
                >
                    You do not have an active class yet.
                </CardContent>
            </Card>
        </section>

        <section class="space-y-4">
            <div class="flex items-center gap-2">
                <History class="size-5 text-muted-foreground" />
                <h2 class="text-sm font-semibold tracking-wide uppercase">
                    Completed / history
                </h2>
            </div>

            <div v-if="historyClasses.length" class="grid gap-4 lg:grid-cols-2">
                <Card v-for="item in historyClasses" :key="item.id">
                    <CardContent class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">{{ item.name }}</h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ item.program }} · {{ item.course }}
                                </p>
                            </div>
                            <Badge variant="secondary">Read only</Badge>
                        </div>
                        <div>
                            <p class="text-sm font-medium">
                                {{ item.completed_lessons }} /
                                {{ item.total_lessons }} lessons ·
                                {{ item.percentage }}%
                            </p>
                        </div>
                        <Button variant="outline" as-child>
                            <Link :href="item.continue_url"
                                >View learning history</Link
                            >
                        </Button>
                    </CardContent>
                </Card>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Completed classes will appear here.
            </p>
        </section>
    </div>
</template>
