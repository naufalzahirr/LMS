<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Option = { id: number; title: string };
type Item = {
    id: number;
    name: string;
    prerequisites: string[];
    assessment_options: Option[];
    lesson_options: Option[];
    rule: {
        learning_class_assessment_id: number;
        mastery_score: string;
        require_remedial: boolean;
        status: string;
        remedial_lesson_ids: number[];
    } | null;
    save_url: string;
};

const props = defineProps<{
    items: Item[];
    statuses: { value: string; label: string }[];
}>();

const forms = reactive(
    Object.fromEntries(
        props.items.map((item) => [
            item.id,
            {
                learning_class_assessment_id:
                    item.rule?.learning_class_assessment_id?.toString() ?? '',
                mastery_score: item.rule?.mastery_score ?? '80.00',
                require_remedial: item.rule?.require_remedial ?? true,
                status: item.rule?.status ?? 'active',
                remedial_lesson_ids: item.rule?.remedial_lesson_ids ?? [],
            },
        ]),
    ),
);

function toggleLesson(competencyId: number, lessonId: number): void {
    const ids = forms[competencyId].remedial_lesson_ids;
    forms[competencyId].remedial_lesson_ids = ids.includes(lessonId)
        ? ids.filter((id) => id !== lessonId)
        : [...ids, lessonId];
}

function save(item: Item): void {
    router.put(item.save_url, {
        ...forms[item.id],
        learning_class_assessment_id: Number(
            forms[item.id].learning_class_assessment_id,
        ),
    });
}
</script>

<template>
    <Card class="gap-0 overflow-hidden py-0">
        <CardHeader class="py-5">
            <CardTitle>Mastery configuration</CardTitle>
            <p class="text-sm text-muted-foreground">
                Designate one published mastery assessment and the pass score
                for each competency.
            </p>
        </CardHeader>
        <CardContent class="divide-y p-0">
            <div v-for="item in items" :key="item.id" class="space-y-5 p-5">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="font-semibold">{{ item.name }}</p>
                    <Badge v-if="item.prerequisites.length" variant="outline">
                        Requires {{ item.prerequisites.join(', ') }}
                    </Badge>
                </div>
                <div
                    v-if="item.assessment_options.length"
                    class="grid gap-4 md:grid-cols-3"
                >
                    <div class="grid gap-2 md:col-span-2">
                        <Label>Mastery assessment</Label>
                        <Select
                            v-model="
                                forms[item.id].learning_class_assessment_id
                            "
                        >
                            <SelectTrigger class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in item.assessment_options"
                                    :key="option.id"
                                    :value="option.id.toString()"
                                >
                                    {{ option.title }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Required score (%)</Label>
                        <Input
                            v-model="forms[item.id].mastery_score"
                            type="number"
                            min="0.01"
                            max="100"
                            step="0.01"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Status</Label>
                        <Select v-model="forms[item.id].status">
                            <SelectTrigger class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="status in statuses"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <label class="flex items-center gap-2 self-end text-sm">
                        <input
                            v-model="forms[item.id].require_remedial"
                            type="checkbox"
                            class="size-4 rounded border"
                        />
                        Require remedial learning after a failed attempt
                    </label>
                </div>
                <p v-else class="text-sm text-amber-700 dark:text-amber-300">
                    Assign and publish a mastery-purpose assessment for this
                    competency first.
                </p>
                <div v-if="item.lesson_options.length" class="space-y-2">
                    <Label>Default remedial lessons</Label>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label
                            v-for="lesson in item.lesson_options"
                            :key="lesson.id"
                            class="flex items-center gap-2 rounded-md border p-3 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="size-4 rounded border"
                                :checked="
                                    forms[item.id].remedial_lesson_ids.includes(
                                        lesson.id,
                                    )
                                "
                                @change="toggleLesson(item.id, lesson.id)"
                            />
                            {{ lesson.title }}
                        </label>
                    </div>
                </div>
                <Button
                    :disabled="
                        !forms[item.id].learning_class_assessment_id ||
                        !item.assessment_options.length
                    "
                    @click="save(item)"
                >
                    Save mastery rule
                </Button>
            </div>
            <p v-if="!items.length" class="p-6 text-sm text-muted-foreground">
                This course has no active competencies.
            </p>
        </CardContent>
    </Card>
</template>
