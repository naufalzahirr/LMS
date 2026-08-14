<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { AcademicStatusOption } from '@/types/academic';
import type {
    AcceptedAnswerValue,
    AuthoringOptions,
    EditableQuestion,
    QuestionOptionValue,
    QuestionType,
    SelectOption,
} from '@/types/assessment';

const props = defineProps<
    AuthoringOptions & {
        questionTypes: SelectOption<QuestionType>[];
        statuses: AcademicStatusOption[];
        errors: Record<string, string>;
        initial?: EditableQuestion;
    }
>();

const initialBank = props.questionBanks.find(
    (item) => item.id === props.initial?.question_bank_id,
);
const initialCourse = props.courses.find(
    (item) => item.id === initialBank?.course_id,
);
const selection = reactive({
    program_id: initialCourse?.program_id.toString() ?? '',
    course_id: initialCourse?.id.toString() ?? '',
    question_bank_id: props.initial?.question_bank_id.toString() ?? '',
    competency_id: props.initial?.competency_id.toString() ?? '',
});
const questionType = ref<QuestionType>(
    props.initial?.question_type ?? 'multiple_choice',
);
const options = reactive<QuestionOptionValue[]>(
    props.initial?.options.length
        ? props.initial.options.map((item) => ({ ...item }))
        : [
              { option_text: '', is_correct: true, sort_order: 0 },
              { option_text: '', is_correct: false, sort_order: 1 },
          ],
);
const acceptedAnswers = reactive<AcceptedAnswerValue[]>(
    props.initial?.accepted_answers.length
        ? props.initial.accepted_answers.map((item) => ({ ...item }))
        : [{ answer_text: '', case_sensitive: false }],
);
const existingImage = props.initial?.image ?? null;
const removeImage = ref(false);
// Alt text is only required once a file is actually chosen, so the field
// appears as soon as there is an image to describe.
const selectedImageName = ref('');

function onImageSelected(event: Event): void {
    selectedImageName.value =
        (event.target as HTMLInputElement).files?.[0]?.name ?? '';
}

const availableCourses = computed(() =>
    props.courses.filter(
        (item) => item.program_id === Number(selection.program_id),
    ),
);
const availableBanks = computed(() =>
    props.questionBanks.filter(
        (item) => item.course_id === Number(selection.course_id),
    ),
);
const availableCompetencies = computed(() =>
    props.competencies.filter(
        (item) => item.course_id === Number(selection.course_id),
    ),
);
const usesOptions = computed(() =>
    ['multiple_choice', 'multiple_select'].includes(questionType.value),
);

watch(
    () => selection.program_id,
    () => {
        if (
            !availableCourses.value.some(
                (item) => item.id === Number(selection.course_id),
            )
        ) {
            selection.course_id = '';
        }
    },
);
watch(
    () => selection.course_id,
    () => {
        if (
            !availableBanks.value.some(
                (item) => item.id === Number(selection.question_bank_id),
            )
        ) {
            selection.question_bank_id = '';
        }

        if (
            !availableCompetencies.value.some(
                (item) => item.id === Number(selection.competency_id),
            )
        ) {
            selection.competency_id = '';
        }
    },
);

function addOption(): void {
    options.push({
        option_text: '',
        is_correct: false,
        sort_order: options.length,
    });
}

function removeOption(index: number): void {
    if (options.length <= 2) {
        return;
    }

    options.splice(index, 1);
    options.forEach((item, itemIndex) => (item.sort_order = itemIndex));
}

function addAnswer(): void {
    acceptedAnswers.push({ answer_text: '', case_sensitive: false });
}

function removeAnswer(index: number): void {
    if (acceptedAnswers.length <= 1) {
        return;
    }

    acceptedAnswers.splice(index, 1);
}
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-4">
        <div class="grid gap-2">
            <Label for="program_id">Program</Label>
            <Select v-model="selection.program_id" required>
                <SelectTrigger id="program_id" class="w-full"
                    ><SelectValue placeholder="Select a program"
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem
                        v-for="program in programs"
                        :key="program.id"
                        :value="program.id.toString()"
                        >{{ program.name }}</SelectItem
                    ></SelectContent
                >
            </Select>
        </div>
        <div class="grid gap-2">
            <Label for="course_id">Course</Label>
            <Select
                v-model="selection.course_id"
                required
                :disabled="!selection.program_id"
            >
                <SelectTrigger id="course_id" class="w-full"
                    ><SelectValue placeholder="Select a course"
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem
                        v-for="course in availableCourses"
                        :key="course.id"
                        :value="course.id.toString()"
                        >{{ course.name }}</SelectItem
                    ></SelectContent
                >
            </Select>
        </div>
        <div class="grid gap-2">
            <Label for="question_bank_id">Question bank</Label>
            <Select
                v-model="selection.question_bank_id"
                name="question_bank_id"
                required
                :disabled="!selection.course_id"
            >
                <SelectTrigger id="question_bank_id" class="w-full"
                    ><SelectValue placeholder="Select a bank"
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem
                        v-for="bank in availableBanks"
                        :key="bank.id"
                        :value="bank.id.toString()"
                        >{{ bank.name }}</SelectItem
                    ></SelectContent
                >
            </Select>
            <InputError :message="errors.question_bank_id" />
        </div>
        <div class="grid gap-2">
            <Label for="competency_id">Competency</Label>
            <Select
                v-model="selection.competency_id"
                name="competency_id"
                required
                :disabled="!selection.course_id"
            >
                <SelectTrigger id="competency_id" class="w-full"
                    ><SelectValue placeholder="Select a competency"
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem
                        v-for="item in availableCompetencies"
                        :key="item.id"
                        :value="item.id.toString()"
                        >{{ item.code }} — {{ item.name }}</SelectItem
                    ></SelectContent
                >
            </Select>
            <InputError :message="errors.competency_id" />
        </div>
    </div>

    <div class="grid gap-2">
        <Label for="prompt">Question prompt</Label>
        <Textarea
            id="prompt"
            name="prompt"
            :default-value="initial?.prompt"
            rows="5"
            required
            autofocus
        />
        <InputError :message="errors.prompt" />
    </div>

    <div class="space-y-4 rounded-xl border p-4">
        <div>
            <p class="font-medium">Question image (optional)</p>
            <p class="text-sm text-muted-foreground">
                One private image shown above the answer options. JPEG, PNG, or
                WebP up to 10 MB.
            </p>
        </div>

        <div
            v-if="existingImage && !removeImage"
            class="flex flex-wrap items-start gap-4"
        >
            <img
                :src="existingImage.url"
                :alt="existingImage.alt_text"
                class="h-auto w-full max-w-xs rounded-lg border bg-background object-contain"
            />
            <div class="text-sm text-muted-foreground">
                <p>{{ existingImage.original_name }}</p>
                <p class="mt-1">Upload a new file below to replace it.</p>
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="question-image">
                {{ existingImage ? 'Replace image' : 'Upload image' }}
            </Label>
            <Input
                id="question-image"
                name="image"
                type="file"
                accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                :disabled="removeImage"
                @change="onImageSelected"
            />
            <InputError :message="errors.image" />
        </div>

        <div v-if="existingImage || selectedImageName" class="grid gap-2">
            <Label for="image_alt_text">Alt text</Label>
            <Input
                id="image_alt_text"
                name="image_alt_text"
                :default-value="initial?.image?.alt_text ?? ''"
                :disabled="removeImage"
                maxlength="500"
                placeholder="Describe the image for screen readers."
            />
            <InputError :message="errors.image_alt_text" />
        </div>

        <label v-if="existingImage" class="flex items-center gap-2 text-sm">
            <input type="hidden" name="remove_image" value="0" />
            <input
                v-model="removeImage"
                type="checkbox"
                name="remove_image"
                value="1"
                class="size-4"
            />
            Remove the current image on save
        </label>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="grid gap-2 sm:col-span-2">
            <Label for="question_type">Question type</Label>
            <Select v-model="questionType" name="question_type" required>
                <SelectTrigger id="question_type" class="w-full"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem
                        v-for="type in questionTypes"
                        :key="type.value"
                        :value="type.value"
                        >{{ type.label }}</SelectItem
                    ></SelectContent
                >
            </Select>
            <InputError :message="errors.question_type" />
        </div>
        <div class="grid gap-2">
            <Label for="default_points">Default points</Label>
            <Input
                id="default_points"
                name="default_points"
                type="number"
                step="0.01"
                min="0.01"
                :default-value="initial?.default_points ?? '1.00'"
                required
            />
            <InputError :message="errors.default_points" />
        </div>
        <div class="grid gap-2">
            <Label for="sort_order">Sort order</Label>
            <Input
                id="sort_order"
                name="sort_order"
                type="number"
                min="0"
                :default-value="initial?.sort_order ?? 0"
                required
            />
            <InputError :message="errors.sort_order" />
        </div>
    </div>

    <div v-if="usesOptions" class="space-y-4 rounded-xl border p-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-medium">Answer options</p>
                <p class="text-sm text-muted-foreground">
                    {{
                        questionType === 'multiple_choice'
                            ? 'Mark exactly one correct option.'
                            : 'Mark one or more correct options.'
                    }}
                </p>
            </div>
            <Button type="button" size="sm" variant="outline" @click="addOption"
                ><Plus /> Add option</Button
            >
        </div>
        <div
            v-for="(option, index) in options"
            :key="option.id ?? index"
            class="flex items-start gap-3"
        >
            <label class="mt-2 flex items-center gap-2 text-sm">
                <input
                    type="hidden"
                    :name="`options[${index}][is_correct]`"
                    value="0"
                />
                <input
                    v-model="option.is_correct"
                    type="checkbox"
                    :name="`options[${index}][is_correct]`"
                    value="1"
                    class="size-4"
                />
                Correct
            </label>
            <div class="grid flex-1 gap-1">
                <Input
                    v-model="option.option_text"
                    :name="`options[${index}][option_text]`"
                    :placeholder="`Option ${index + 1}`"
                    required
                />
                <input
                    type="hidden"
                    :name="`options[${index}][sort_order]`"
                    :value="index"
                />
                <InputError :message="errors[`options.${index}.option_text`]" />
            </div>
            <Button
                type="button"
                size="icon-sm"
                variant="ghost"
                :disabled="options.length <= 2"
                aria-label="Remove option"
                @click="removeOption(index)"
                ><Trash2
            /></Button>
        </div>
        <InputError :message="errors.options" />
    </div>

    <div
        v-if="questionType === 'true_false'"
        class="grid max-w-sm gap-2 rounded-xl border p-4"
    >
        <Label for="correct_boolean">Correct answer</Label>
        <Select
            name="correct_boolean"
            :default-value="initial?.correct_boolean === false ? '0' : '1'"
            required
        >
            <SelectTrigger id="correct_boolean" class="w-full"
                ><SelectValue
            /></SelectTrigger>
            <SelectContent
                ><SelectItem value="1">True</SelectItem
                ><SelectItem value="0">False</SelectItem></SelectContent
            >
        </Select>
        <InputError :message="errors.correct_boolean" />
    </div>

    <div
        v-if="questionType === 'short_answer'"
        class="space-y-4 rounded-xl border p-4"
    >
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-medium">Accepted answers</p>
                <p class="text-sm text-muted-foreground">
                    Add every answer learners may submit.
                </p>
            </div>
            <Button type="button" size="sm" variant="outline" @click="addAnswer"
                ><Plus /> Add answer</Button
            >
        </div>
        <div
            v-for="(answer, index) in acceptedAnswers"
            :key="answer.id ?? index"
            class="flex items-start gap-3"
        >
            <div class="grid flex-1 gap-1">
                <Input
                    v-model="answer.answer_text"
                    :name="`accepted_answers[${index}][answer_text]`"
                    :placeholder="`Accepted answer ${index + 1}`"
                    required
                />
                <InputError
                    :message="errors[`accepted_answers.${index}.answer_text`]"
                />
            </div>
            <label class="mt-2 flex items-center gap-2 text-sm">
                <input
                    type="hidden"
                    :name="`accepted_answers[${index}][case_sensitive]`"
                    value="0"
                />
                <input
                    v-model="answer.case_sensitive"
                    type="checkbox"
                    :name="`accepted_answers[${index}][case_sensitive]`"
                    value="1"
                    class="size-4"
                />
                Case-sensitive
            </label>
            <Button
                type="button"
                size="icon-sm"
                variant="ghost"
                :disabled="acceptedAnswers.length <= 1"
                aria-label="Remove answer"
                @click="removeAnswer(index)"
                ><Trash2
            /></Button>
        </div>
        <InputError :message="errors.accepted_answers" />
    </div>

    <div
        v-if="questionType === 'essay'"
        class="rounded-xl border bg-muted/30 p-4 text-sm text-muted-foreground"
    >
        Essay questions do not use an automatic answer key.
    </div>

    <div class="grid gap-2">
        <Label for="explanation">Answer explanation</Label>
        <Textarea
            id="explanation"
            name="explanation"
            :default-value="initial?.explanation ?? ''"
        />
        <InputError :message="errors.explanation" />
    </div>
    <div class="grid max-w-xs gap-2">
        <Label for="status">Status</Label>
        <Select
            name="status"
            :default-value="initial?.status ?? 'active'"
            required
        >
            <SelectTrigger id="status" class="w-full"
                ><SelectValue
            /></SelectTrigger>
            <SelectContent
                ><SelectItem
                    v-for="status in statuses"
                    :key="status.value"
                    :value="status.value"
                    >{{ status.label }}</SelectItem
                ></SelectContent
            >
        </Select>
        <InputError :message="errors.status" />
    </div>
</template>
