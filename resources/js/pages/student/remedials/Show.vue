<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, CheckCircle2, Circle } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Item = {
    id: number;
    title: string;
    module: string;
    completed_at: string | null;
    lesson_url: string | null;
    complete_url: string;
};
type Remedial = {
    id: number;
    competency: string;
    status: string;
    latest_score: string;
    required_score: string;
    attempt_number: number;
    assigned_at: string;
    completed_at: string | null;
    notes: string | null;
    class_url: string;
    lessons: Item[];
};

defineProps<{ remedial: Remedial }>();

function complete(item: Item): void {
    router.patch(item.complete_url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Remedial · ${remedial.competency}`" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="`Remedial learning: ${remedial.competency}`"
                description="Complete every assigned item before your next mastery attempt."
            />
            <Button variant="outline" as-child
                ><Link :href="remedial.class_url"
                    ><ArrowLeft /> Class</Link
                ></Button
            >
        </div>
        <Card>
            <CardContent class="grid gap-4 pt-2 sm:grid-cols-4">
                <div>
                    <p class="text-sm text-muted-foreground">Status</p>
                    <Badge class="mt-1">{{ remedial.status }}</Badge>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Attempt</p>
                    <p class="font-medium">#{{ remedial.attempt_number }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Latest score</p>
                    <p class="font-medium">{{ remedial.latest_score }}%</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Target</p>
                    <p class="font-medium">{{ remedial.required_score }}%</p>
                </div>
            </CardContent>
        </Card>
        <Card>
            <CardHeader
                ><CardTitle>Your remedial checklist</CardTitle></CardHeader
            >
            <CardContent class="space-y-3">
                <div
                    v-for="item in remedial.lessons"
                    :key="item.id"
                    class="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <CheckCircle2
                            v-if="item.completed_at"
                            class="mt-0.5 size-5 text-emerald-600"
                        />
                        <Circle
                            v-else
                            class="mt-0.5 size-5 text-muted-foreground"
                        />
                        <div>
                            <p class="font-medium">{{ item.title }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ item.module }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            v-if="item.lesson_url"
                            variant="outline"
                            as-child
                            ><Link :href="item.lesson_url"
                                >Review lesson <ArrowRight /></Link
                        ></Button>
                        <Button
                            v-if="
                                !item.completed_at &&
                                remedial.status === 'assigned'
                            "
                            @click="complete(item)"
                            >Mark complete</Button
                        >
                    </div>
                </div>
                <p
                    v-if="!remedial.lessons.length"
                    class="text-sm text-muted-foreground"
                >
                    Your tutor will complete this intervention after working
                    with you directly.
                </p>
                <div
                    v-if="remedial.notes"
                    class="rounded-lg bg-muted p-4 text-sm whitespace-pre-line"
                >
                    {{ remedial.notes }}
                </div>
            </CardContent>
        </Card>
    </div>
</template>
