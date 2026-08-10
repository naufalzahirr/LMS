<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Summary = {
    id: number;
    student: string;
    email: string;
    competency: string;
    status: string;
    latest_score: string;
    required_score: string;
    attempt_number: number;
    assigned_at: string;
    completed_at: string | null;
    notes: string | null;
};
type Item = {
    id: number;
    lesson_id: number;
    title: string;
    module: string;
    completed_at: string | null;
    remove_url: string;
};

const props = defineProps<{
    assignment: Summary;
    lessons: Item[];
    lessonOptions: { id: number; title: string; module: string }[];
    lessonStoreUrl: string;
    updateUrl: string;
    completeUrl: string;
    backUrl: string;
}>();

const notes = ref(props.assignment.notes ?? '');
const lessonId = ref('');

function saveNotes(): void {
    router.patch(
        props.updateUrl,
        { notes: notes.value },
        { preserveScroll: true },
    );
}

function addLesson(): void {
    router.post(
        props.lessonStoreUrl,
        { lesson_id: Number(lessonId.value) },
        { preserveScroll: true },
    );
}

function removeLesson(item: Item): void {
    if (window.confirm(`Remove ${item.title} from this intervention?`)) {
        router.delete(item.remove_url, { preserveScroll: true });
    }
}

function complete(): void {
    if (window.confirm('Mark this remedial intervention complete?')) {
        router.patch(props.completeUrl, {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="`Remedial · ${assignment.student}`" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="`Remedial: ${assignment.competency}`"
                :description="`${assignment.student} · ${assignment.email}`"
            />
            <Button variant="outline" as-child>
                <Link :href="backUrl"><ArrowLeft /> Class mastery</Link>
            </Button>
        </div>

        <Card>
            <CardContent class="grid gap-4 pt-2 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <p class="text-sm text-muted-foreground">Status</p>
                    <Badge class="mt-1">{{ assignment.status }}</Badge>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Failed attempt</p>
                    <p class="font-medium">#{{ assignment.attempt_number }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Latest score</p>
                    <p class="font-medium">{{ assignment.latest_score }}%</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Required</p>
                    <p class="font-medium">{{ assignment.required_score }}%</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Assigned</p>
                    <p class="font-medium">{{ assignment.assigned_at }}</p>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader><CardTitle>Remedial lessons</CardTitle></CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-if="assignment.status === 'assigned'"
                        class="flex items-end gap-3"
                    >
                        <div class="grid flex-1 gap-2">
                            <Label>Add lesson</Label>
                            <Select v-model="lessonId">
                                <SelectTrigger class="w-full"
                                    ><SelectValue placeholder="Choose a lesson"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in lessonOptions"
                                        :key="option.id"
                                        :value="option.id.toString()"
                                    >
                                        {{ option.module }} · {{ option.title }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button :disabled="!lessonId" @click="addLesson"
                            ><Plus /> Add</Button
                        >
                    </div>
                    <div class="divide-y rounded-lg border">
                        <div
                            v-for="item in lessons"
                            :key="item.id"
                            class="flex items-center justify-between gap-3 p-3"
                        >
                            <div>
                                <p class="font-medium">{{ item.title }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ item.module }} ·
                                    {{
                                        item.completed_at
                                            ? 'Completed'
                                            : 'Pending'
                                    }}
                                </p>
                            </div>
                            <Button
                                v-if="assignment.status === 'assigned'"
                                size="icon-sm"
                                variant="ghost"
                                @click="removeLesson(item)"
                                ><Trash2
                            /></Button>
                        </div>
                        <p
                            v-if="!lessons.length"
                            class="p-4 text-sm text-muted-foreground"
                        >
                            No lesson items. Tutor or Admin completion is
                            required.
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader
                    ><CardTitle>Intervention notes</CardTitle></CardHeader
                >
                <CardContent class="space-y-4">
                    <Textarea
                        v-model="notes"
                        rows="8"
                        :disabled="assignment.status !== 'assigned'"
                    />
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="assignment.status === 'assigned'"
                            variant="outline"
                            @click="saveNotes"
                            >Save notes</Button
                        >
                        <Button
                            v-if="assignment.status === 'assigned'"
                            @click="complete"
                            ><CheckCircle2 /> Complete intervention</Button
                        >
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
