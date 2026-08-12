<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type {
    LessonCheckpointAuthorInput,
    LessonCheckpointAuthorPayload,
    LessonCheckpointOption,
    LessonCheckpointType,
} from '@/types/lesson-content';

const props = defineProps<{
    open: boolean;
    editing: boolean;
    initial: LessonCheckpointAuthorPayload | null;
    busy: boolean;
    serverError: string;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
    save: [value: LessonCheckpointAuthorInput];
    remove: [];
}>();

const checkpointType = ref<LessonCheckpointType>('multiple_choice');
const prompt = ref('');
const explanation = ref('');
const options = ref<LessonCheckpointOption[]>([]);
const correctOptionIds = ref<string[]>([]);
const correctBoolean = ref<boolean | null>(true);
const acceptedAnswers = ref<string[]>(['']);
const errors = ref<Record<string, string>>({});
const usesOptions = computed(() =>
    ['multiple_choice', 'multiple_select'].includes(checkpointType.value),
);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        const initial = props.initial;
        checkpointType.value = initial?.type ?? 'multiple_choice';
        prompt.value = initial?.prompt ?? '';
        explanation.value = initial?.explanation ?? '';
        options.value = initial?.options.length
            ? initial.options.map((option) => ({ ...option }))
            : [newOption(), newOption()];
        correctOptionIds.value = [...(initial?.correct_option_ids ?? [])];
        correctBoolean.value = initial?.correct_boolean ?? true;
        acceptedAnswers.value = initial?.accepted_answers.length
            ? [...initial.accepted_answers]
            : [''];
        errors.value = {};
    },
);

watch(checkpointType, (type) => {
    errors.value = {};

    if (['multiple_choice', 'multiple_select'].includes(type)) {
        while (options.value.length < 2) {
            options.value.push(newOption());
        }
    }
});

function newOption(): LessonCheckpointOption {
    return { id: globalThis.crypto.randomUUID(), text: '' };
}

function addOption(): void {
    if (options.value.length < 10) {
        options.value.push(newOption());
    }
}

function removeOption(index: number): void {
    if (options.value.length <= 2) {
        return;
    }

    const [removed] = options.value.splice(index, 1);
    correctOptionIds.value = correctOptionIds.value.filter(
        (id) => id !== removed?.id,
    );
}

function moveOption(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target < 0 || target >= options.value.length) {
        return;
    }

    const [option] = options.value.splice(index, 1);

    if (option) {
        options.value.splice(target, 0, option);
    }
}

function selectSingleOption(id: string): void {
    correctOptionIds.value = [id];
}

function toggleCorrectOption(id: string, selected: boolean): void {
    correctOptionIds.value = selected
        ? [...correctOptionIds.value, id]
        : correctOptionIds.value.filter((value) => value !== id);
}

function addAcceptedAnswer(): void {
    if (acceptedAnswers.value.length < 10) {
        acceptedAnswers.value.push('');
    }
}

function removeAcceptedAnswer(index: number): void {
    if (acceptedAnswers.value.length > 1) {
        acceptedAnswers.value.splice(index, 1);
    }
}

function submit(): void {
    errors.value = validate();

    if (Object.keys(errors.value).length > 0) {
        return;
    }

    const value: LessonCheckpointAuthorInput = {
        checkpoint_type: checkpointType.value,
        prompt: prompt.value.trim(),
        explanation: explanation.value.trim() || null,
    };

    if (usesOptions.value) {
        value.options = options.value.map((option) => ({
            id: option.id,
            text: option.text.trim(),
        }));
        value.correct_option_ids = [...correctOptionIds.value];
    } else if (checkpointType.value === 'true_false') {
        value.correct_boolean = correctBoolean.value === true;
    } else {
        value.accepted_answers = acceptedAnswers.value.map((answer) =>
            answer.trim(),
        );
    }

    emit('save', value);
}

function validate(): Record<string, string> {
    const next: Record<string, string> = {};

    if (!prompt.value.trim()) {
        next.prompt =
            checkpointType.value === 'true_false'
                ? 'A statement is required.'
                : 'A question or prompt is required.';
    }

    if (usesOptions.value) {
        if (options.value.some((option) => !option.text.trim())) {
            next.options = 'Every answer option needs text.';
        }

        if (
            checkpointType.value === 'multiple_choice' &&
            correctOptionIds.value.length !== 1
        ) {
            next.correct = 'Choose exactly one correct answer.';
        }

        if (
            checkpointType.value === 'multiple_select' &&
            correctOptionIds.value.length < 1
        ) {
            next.correct = 'Choose at least one correct answer.';
        }
    }

    if (
        checkpointType.value === 'true_false' &&
        correctBoolean.value === null
    ) {
        next.correct = 'Choose whether the statement is true or false.';
    }

    if (
        checkpointType.value === 'fill_blank' &&
        acceptedAnswers.value.some((answer) => !answer.trim())
    ) {
        next.answers = 'Accepted answers cannot be blank.';
    }

    return next;
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-h-[calc(100dvh-2rem)] overflow-hidden p-0 sm:max-w-3xl"
        >
            <form
                class="flex max-h-[calc(100dvh-2rem)] min-h-0 flex-col"
                novalidate
                @submit.prevent="submit"
            >
                <div class="min-h-0 flex-1 space-y-6 overflow-y-auto p-6">
                    <DialogHeader>
                        <DialogTitle>{{
                            editing ? 'Edit checkpoint' : 'Add checkpoint'
                        }}</DialogTitle>
                        <DialogDescription>
                            Add a formative question with immediate feedback.
                            Checkpoints do not affect assessment grades.
                        </DialogDescription>
                    </DialogHeader>

                    <fieldset class="space-y-2" :disabled="busy">
                        <legend class="text-sm font-medium">
                            Checkpoint type
                        </legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="choice in [
                                    ['multiple_choice', 'Multiple Choice'],
                                    ['multiple_select', 'Multiple Select'],
                                    ['true_false', 'True / False'],
                                    ['fill_blank', 'Fill in the Blank'],
                                ] as const"
                                :key="choice[0]"
                                class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm has-checked:border-primary has-checked:bg-primary/5"
                            >
                                <input
                                    v-model="checkpointType"
                                    type="radio"
                                    name="checkpoint-type"
                                    :value="choice[0]"
                                    class="size-4 accent-primary"
                                />
                                <span class="font-medium">{{ choice[1] }}</span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="grid gap-2">
                        <Label for="checkpoint-prompt">{{
                            checkpointType === 'true_false'
                                ? 'Statement'
                                : 'Question / prompt'
                        }}</Label>
                        <Textarea
                            id="checkpoint-prompt"
                            v-model="prompt"
                            rows="3"
                            maxlength="5000"
                            :disabled="busy"
                            :aria-invalid="Boolean(errors.prompt)"
                            @input="delete errors.prompt"
                        />
                        <p
                            v-if="errors.prompt"
                            class="text-sm text-destructive"
                        >
                            {{ errors.prompt }}
                        </p>
                    </div>

                    <fieldset
                        v-if="usesOptions"
                        class="space-y-3"
                        :disabled="busy"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <legend class="text-sm font-medium">
                                Answer options
                            </legend>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                :disabled="options.length >= 10"
                                @click="addOption"
                            >
                                <Plus /> Add option
                            </Button>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{
                                checkpointType === 'multiple_select'
                                    ? 'Select every correct option. Learners must match the exact set.'
                                    : 'Select the single correct option.'
                            }}
                        </p>
                        <div
                            v-for="(option, index) in options"
                            :key="option.id"
                            class="flex items-center gap-2 rounded-lg border p-2"
                        >
                            <input
                                v-if="checkpointType === 'multiple_choice'"
                                type="radio"
                                name="correct-checkpoint-option"
                                :checked="correctOptionIds[0] === option.id"
                                :aria-label="`Mark option ${index + 1} correct`"
                                class="size-4 shrink-0 accent-primary"
                                @change="selectSingleOption(option.id)"
                            />
                            <input
                                v-else
                                type="checkbox"
                                :checked="correctOptionIds.includes(option.id)"
                                :aria-label="`Mark option ${index + 1} correct`"
                                class="size-4 shrink-0 accent-primary"
                                @change="
                                    toggleCorrectOption(
                                        option.id,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            <Label
                                :for="`checkpoint-option-${option.id}`"
                                class="sr-only"
                            >
                                Option {{ index + 1 }}
                            </Label>
                            <Input
                                :id="`checkpoint-option-${option.id}`"
                                v-model="option.text"
                                :placeholder="`Option ${index + 1}`"
                                maxlength="500"
                                @input="delete errors.options"
                            />
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :disabled="index === 0"
                                :aria-label="`Move option ${index + 1} up`"
                                @click="moveOption(index, -1)"
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :disabled="index === options.length - 1"
                                :aria-label="`Move option ${index + 1} down`"
                                @click="moveOption(index, 1)"
                            >
                                <ArrowDown />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :disabled="options.length <= 2"
                                :aria-label="`Remove option ${index + 1}`"
                                @click="removeOption(index)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                        <p
                            v-if="errors.options"
                            class="text-sm text-destructive"
                        >
                            {{ errors.options }}
                        </p>
                        <p
                            v-if="errors.correct"
                            class="text-sm text-destructive"
                        >
                            {{ errors.correct }}
                        </p>
                    </fieldset>

                    <fieldset
                        v-else-if="checkpointType === 'true_false'"
                        class="space-y-2"
                        :disabled="busy"
                    >
                        <legend class="text-sm font-medium">
                            Correct answer
                        </legend>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                v-for="choice in [
                                    [true, 'True'],
                                    [false, 'False'],
                                ] as const"
                                :key="String(choice[0])"
                                class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 has-checked:border-primary has-checked:bg-primary/5"
                            >
                                <input
                                    type="radio"
                                    name="checkpoint-boolean-answer"
                                    :checked="correctBoolean === choice[0]"
                                    class="size-4 accent-primary"
                                    @change="correctBoolean = choice[0]"
                                />
                                {{ choice[1] }}
                            </label>
                        </div>
                    </fieldset>

                    <fieldset v-else class="space-y-3" :disabled="busy">
                        <div class="flex items-center justify-between gap-3">
                            <legend class="text-sm font-medium">
                                Accepted answers
                            </legend>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                :disabled="acceptedAnswers.length >= 10"
                                @click="addAcceptedAnswer"
                            >
                                <Plus /> Add alternative
                            </Button>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Matching ignores capitalization and surrounding
                            spaces.
                        </p>
                        <div
                            v-for="(_, index) in acceptedAnswers"
                            :key="index"
                            class="flex items-center gap-2"
                        >
                            <Label
                                :for="`checkpoint-answer-${index}`"
                                class="sr-only"
                            >
                                Accepted answer {{ index + 1 }}
                            </Label>
                            <Input
                                :id="`checkpoint-answer-${index}`"
                                v-model="acceptedAnswers[index]"
                                :placeholder="
                                    index === 0
                                        ? 'Accepted answer'
                                        : 'Accepted alternative'
                                "
                                maxlength="500"
                                @input="delete errors.answers"
                            />
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :disabled="acceptedAnswers.length <= 1"
                                :aria-label="`Remove accepted answer ${index + 1}`"
                                @click="removeAcceptedAnswer(index)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                        <p
                            v-if="errors.answers"
                            class="text-sm text-destructive"
                        >
                            {{ errors.answers }}
                        </p>
                    </fieldset>

                    <div class="grid gap-2">
                        <Label for="checkpoint-explanation">
                            Explanation / feedback (optional)
                        </Label>
                        <Textarea
                            id="checkpoint-explanation"
                            v-model="explanation"
                            rows="3"
                            maxlength="10000"
                            :disabled="busy"
                            placeholder="Shown after a learner checks an answer."
                        />
                    </div>

                    <p
                        v-if="serverError"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ serverError }}
                    </p>
                </div>

                <DialogFooter
                    class="shrink-0 border-t bg-background px-6 py-4 sm:justify-between"
                >
                    <Button
                        v-if="editing"
                        type="button"
                        variant="destructive"
                        :disabled="busy"
                        @click="emit('remove')"
                    >
                        <Trash2 /> Remove checkpoint
                    </Button>
                    <span v-else />
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="busy"
                            @click="emit('update:open', false)"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="busy">
                            {{
                                busy
                                    ? 'Saving…'
                                    : editing
                                      ? 'Update checkpoint'
                                      : 'Insert checkpoint'
                            }}
                        </Button>
                    </div>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
