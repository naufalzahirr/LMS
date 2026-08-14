<script setup lang="ts">
import { CheckCircle2, CircleHelp, RotateCcw, XCircle } from '@lucide/vue';
import { computed, inject, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { lessonCheckpointMasteryKey } from '@/lib/lessonCheckpointMastery';
import type {
    LessonCheckpointPayload,
    LessonCheckpointResult,
    LessonCheckpointStudentState,
    LessonNode,
} from '@/types/lesson-content';

const props = defineProps<{ node: LessonNode }>();
const checkpoint = computed<LessonCheckpointPayload | null>(() => {
    const value = props.node.attrs?.checkpoint;

    return isCheckpointPayload(value) ? value : null;
});
const studentState = computed<LessonCheckpointStudentState | null>(() => {
    const value = checkpoint.value;

    return value && 'interactive' in value && value.interactive ? value : null;
});
const masteryContext = inject(lessonCheckpointMasteryKey, null);
const selectedOption = ref('');
const selectedOptions = ref<string[]>([]);
const selectedBoolean = ref<'true' | 'false' | ''>('');
// The submitted value stays the boolean the server evaluates; only its label
// is localised, so the checkpoint answer contract is unchanged.
const booleanChoices = [
    { value: 'true', label: 'Benar' },
    { value: 'false', label: 'Salah' },
] as const;
const fillAnswer = ref('');
const submitting = ref(false);
const error = ref('');
const result = ref<LessonCheckpointResult | null>(null);
const mastered = computed(() => {
    const id = checkpoint.value?.id;
    const synchronized = id ? masteryContext?.state.value?.[id] : undefined;

    return (
        synchronized ??
        result.value?.mastered ??
        studentState.value?.mastered ??
        false
    );
});
const attemptCount = computed(
    () => result.value?.attempt_count ?? studentState.value?.attempt_count ?? 0,
);
const visibleExplanation = computed(
    () => result.value?.explanation ?? checkpoint.value?.explanation ?? null,
);

function toggleOption(id: string, selected: boolean): void {
    selectedOptions.value = selected
        ? [...selectedOptions.value, id]
        : selectedOptions.value.filter((value) => value !== id);
}

async function submit(): Promise<void> {
    const state = studentState.value;
    const value = answer();

    if (!state?.can_submit || value === null) {
        error.value = state?.can_submit
            ? 'Pilih atau isi jawabanmu dulu, ya.'
            : 'Pelajaran ini hanya bisa dibaca.';

        return;
    }

    submitting.value = true;
    error.value = '';

    try {
        const response = await fetch(state.submit_url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ answer: value }),
            credentials: 'same-origin',
        });
        const payload = (await response.json()) as LessonCheckpointResult & {
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (!response.ok || typeof payload.correct !== 'boolean') {
            error.value =
                Object.values(payload.errors ?? {})[0]?.[0] ??
                payload.message ??
                'Jawaban belum bisa diperiksa.';

            return;
        }

        result.value = payload;

        if (checkpoint.value) {
            masteryContext?.update({
                checkpointId: checkpoint.value.id,
                mastered: payload.mastered,
            });
        }
    } catch {
        error.value =
            'Jawaban belum bisa diperiksa. Periksa koneksimu, lalu coba lagi.';
    } finally {
        submitting.value = false;
    }
}

function answer(): string | boolean | string[] | null {
    const type = checkpoint.value?.type;

    if (type === 'multiple_choice') {
        return selectedOption.value || null;
    }

    if (type === 'multiple_select') {
        return selectedOptions.value.length ? selectedOptions.value : null;
    }

    if (type === 'true_false') {
        return selectedBoolean.value === ''
            ? null
            : selectedBoolean.value === 'true';
    }

    return fillAnswer.value.trim() || null;
}

function retry(): void {
    result.value = null;
    error.value = '';
}

function isCheckpointPayload(value: unknown): value is LessonCheckpointPayload {
    return (
        typeof value === 'object' &&
        value !== null &&
        typeof (value as { id?: unknown }).id === 'number' &&
        typeof (value as { prompt?: unknown }).prompt === 'string' &&
        Array.isArray((value as { options?: unknown }).options)
    );
}

function csrfToken(): string {
    return (
        globalThis.document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}
</script>

<template>
    <section
        v-if="checkpoint"
        class="my-8 overflow-hidden rounded-xl border border-violet-200 bg-violet-50/60 shadow-sm dark:border-violet-900 dark:bg-violet-950/20"
        :aria-labelledby="`checkpoint-${checkpoint.id}-prompt`"
    >
        <div
            class="border-b border-violet-200 px-4 py-3 sm:px-5 dark:border-violet-900"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span
                    class="inline-flex items-center gap-2 text-xs font-semibold tracking-wide text-violet-800 uppercase dark:text-violet-300"
                >
                    <CircleHelp class="size-4" /> Ayo Coba ·
                    {{ checkpoint.type_label }}
                </span>
                <Badge
                    v-if="mastered"
                    variant="outline"
                    class="gap-1 border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                >
                    <CheckCircle2 class="size-3.5" /> Sudah Benar
                </Badge>
                <Badge
                    v-else-if="studentState && attemptCount > 0"
                    variant="secondary"
                >
                    Sudah Dicoba
                </Badge>
                <Badge v-else-if="!studentState" variant="secondary">
                    Pratinjau Penulis
                </Badge>
            </div>
        </div>

        <div class="space-y-5 p-4 sm:p-5">
            <p
                :id="`checkpoint-${checkpoint.id}-prompt`"
                class="text-base leading-7 font-semibold text-foreground sm:text-lg"
            >
                {{ checkpoint.prompt }}
            </p>
            <p
                v-if="checkpoint.type === 'multiple_select'"
                class="text-sm text-muted-foreground"
            >
                Pilih semua jawaban yang benar.
            </p>

            <fieldset
                v-if="
                    ['multiple_choice', 'multiple_select'].includes(
                        checkpoint.type,
                    )
                "
                class="space-y-2"
                :disabled="submitting || !studentState?.can_submit"
            >
                <legend class="sr-only">Pilihan jawaban</legend>
                <label
                    v-for="(option, index) in checkpoint.options"
                    :key="option.id"
                    class="flex cursor-pointer items-start gap-3 rounded-lg border bg-background p-3 text-sm leading-6 transition-colors has-checked:border-violet-500 has-checked:bg-violet-50 dark:has-checked:bg-violet-950/40"
                >
                    <input
                        v-if="checkpoint.type === 'multiple_choice'"
                        v-model="selectedOption"
                        type="radio"
                        :name="`checkpoint-${checkpoint.id}`"
                        :value="option.id"
                        class="mt-1 size-4 shrink-0 accent-primary"
                    />
                    <input
                        v-else
                        type="checkbox"
                        :checked="selectedOptions.includes(option.id)"
                        class="mt-1 size-4 shrink-0 accent-primary"
                        @change="
                            toggleOption(
                                option.id,
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    <span
                        ><span class="sr-only">Pilihan {{ index + 1 }}:</span
                        >{{ option.text }}</span
                    >
                </label>
            </fieldset>

            <fieldset
                v-else-if="checkpoint.type === 'true_false'"
                class="grid grid-cols-2 gap-2"
                :disabled="submitting || !studentState?.can_submit"
            >
                <legend class="sr-only">Pilih benar atau salah</legend>
                <label
                    v-for="choice in booleanChoices"
                    :key="choice.value"
                    class="flex cursor-pointer items-center gap-3 rounded-lg border bg-background p-3 has-checked:border-violet-500 has-checked:bg-violet-50 dark:has-checked:bg-violet-950/40"
                >
                    <input
                        v-model="selectedBoolean"
                        type="radio"
                        :name="`checkpoint-${checkpoint.id}`"
                        :value="choice.value"
                        class="size-4 accent-primary"
                    />
                    {{ choice.label }}
                </label>
            </fieldset>

            <div v-else class="grid gap-2">
                <Label :for="`checkpoint-${checkpoint.id}-answer`">
                    Jawabanmu
                </Label>
                <Input
                    :id="`checkpoint-${checkpoint.id}-answer`"
                    v-model="fillAnswer"
                    type="text"
                    autocomplete="off"
                    :disabled="submitting || !studentState?.can_submit"
                    class="w-full"
                    @keydown.enter.prevent="submit"
                />
            </div>

            <div v-if="studentState" class="flex flex-wrap items-center gap-3">
                <Button
                    type="button"
                    :disabled="submitting || !studentState.can_submit"
                    @click="submit"
                >
                    {{ submitting ? 'Memeriksa…' : 'Periksa Jawaban' }}
                </Button>
                <Button
                    v-if="result"
                    type="button"
                    variant="ghost"
                    :disabled="submitting || !studentState.can_submit"
                    @click="retry"
                >
                    <RotateCcw /> Coba Lagi
                </Button>
                <span
                    v-if="attemptCount > 0"
                    class="text-xs text-muted-foreground"
                >
                    {{ attemptCount }} kali mencoba
                </span>
            </div>

            <div
                v-if="result"
                aria-live="polite"
                class="rounded-lg border p-4"
                :class="
                    result.correct
                        ? 'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100'
                        : 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100'
                "
            >
                <p class="flex items-center gap-2 font-semibold">
                    <CheckCircle2 v-if="result.correct" class="size-5" />
                    <XCircle v-else class="size-5" />
                    {{ result.feedback }}
                </p>
                <p v-if="visibleExplanation" class="mt-2 text-sm leading-6">
                    {{ visibleExplanation }}
                </p>
            </div>
            <div
                v-else-if="mastered && visibleExplanation"
                class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-100"
            >
                <p class="font-semibold">Sudah pernah kamu jawab benar</p>
                <p class="mt-1">{{ visibleExplanation }}</p>
            </div>
            <p
                v-else-if="!studentState && visibleExplanation"
                class="text-sm leading-6 text-muted-foreground"
            >
                <span class="font-medium text-foreground">Penjelasan:</span>
                {{ visibleExplanation }}
            </p>

            <p v-if="error" role="alert" class="text-sm text-destructive">
                {{ error }}
            </p>
        </div>
    </section>
</template>
