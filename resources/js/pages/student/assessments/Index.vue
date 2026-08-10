<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, ClipboardList } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { StudentAssessmentCard } from '@/types/assessment-attempt';

defineProps<{
    learningClass: { id: number; name: string; course: string; url: string };
    assessments: StudentAssessmentCard[];
}>();
</script>

<template>
    <Head :title="`${learningClass.name} assessments`" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                title="Assessments"
                :description="`${learningClass.name} · ${learningClass.course}`"
            />
            <Button variant="outline" as-child>
                <Link :href="learningClass.url"><ArrowLeft /> Class</Link>
            </Button>
        </div>

        <div v-if="assessments.length" class="grid gap-4 lg:grid-cols-2">
            <Card v-for="assessment in assessments" :key="assessment.id">
                <CardHeader>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <CardTitle>{{ assessment.title }}</CardTitle>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ assessment.competency }} ·
                                {{ assessment.purpose }}
                            </p>
                        </div>
                        <Badge variant="secondary">{{
                            assessment.availability
                        }}</Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                        <div>
                            <p class="text-muted-foreground">Questions</p>
                            <p class="font-medium">
                                {{ assessment.question_count }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Points</p>
                            <p class="font-medium">
                                {{ assessment.total_points }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Attempts</p>
                            <p class="font-medium">
                                {{ assessment.attempts_used }} /
                                {{ assessment.max_attempts }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Closes</p>
                            <p class="font-medium">
                                {{ assessment.closes_at ?? 'No deadline' }}
                            </p>
                        </div>
                    </div>
                    <Button class="w-full" as-child>
                        <Link :href="assessment.intro_url">
                            View assessment <ArrowRight />
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        </div>
        <Card v-else>
            <CardContent
                class="flex flex-col items-center gap-3 py-12 text-center text-muted-foreground"
            >
                <ClipboardList class="size-9" />
                <p>No assessments are assigned to this class.</p>
            </CardContent>
        </Card>
    </div>
</template>
