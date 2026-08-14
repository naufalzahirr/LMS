<?php

namespace App\Services;

use App\Enums\LessonCheckpointType;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCheckpoint;
use App\Models\LessonCheckpointAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LessonCheckpointService
{
    /**
     * Shown when an author left the matching feedback field empty, including
     * every checkpoint authored before those fields existed.
     */
    public const DEFAULT_CORRECT_FEEDBACK = 'Benar!';

    public const DEFAULT_INCORRECT_FEEDBACK = 'Belum tepat.';

    /**
     * @param  array{checkpoint_type: LessonCheckpointType, prompt: string, correct_feedback: string|null, incorrect_feedback: string|null, explanation: string|null, configuration: array<string, mixed>, answer_key: array<string, mixed>}  $data
     */
    public function create(Lesson $lesson, User $author, array $data): LessonCheckpoint
    {
        return $lesson->checkpoints()->create([
            ...$data,
            'created_by' => $author->id,
        ]);
    }

    /**
     * @param  array{checkpoint_type: LessonCheckpointType, prompt: string, correct_feedback: string|null, incorrect_feedback: string|null, explanation: string|null, configuration: array<string, mixed>, answer_key: array<string, mixed>}  $data
     */
    public function update(LessonCheckpoint $checkpoint, array $data): LessonCheckpoint
    {
        $checkpoint->fill($data)->save();

        return $checkpoint->refresh();
    }

    /** @return array<string, mixed> */
    public function authorPayload(LessonCheckpoint $checkpoint): array
    {
        return [
            ...$this->publicPayload($checkpoint),
            'correct_feedback' => $checkpoint->correct_feedback,
            'incorrect_feedback' => $checkpoint->incorrect_feedback,
            'explanation' => $checkpoint->explanation,
            'correct_option_ids' => $checkpoint->answer_key['correct_option_ids'] ?? [],
            'correct_boolean' => $checkpoint->answer_key['correct_boolean'] ?? null,
            'accepted_answers' => $checkpoint->answer_key['accepted_answers'] ?? [],
            'update_url' => route('admin.lesson-checkpoints.update', [$checkpoint->lesson_id, $checkpoint]),
        ];
    }

    /** @return array<string, mixed> */
    public function previewPayload(LessonCheckpoint $checkpoint): array
    {
        return [
            ...$this->publicPayload($checkpoint),
            'explanation' => $checkpoint->explanation,
            'interactive' => false,
        ];
    }

    /**
     * @param  Collection<int, LessonCheckpointAttempt>|null  $attempts
     * @return array<string, mixed>
     */
    public function studentPayload(
        LessonCheckpoint $checkpoint,
        Enrollment $enrollment,
        string $submitUrl,
        bool $canSubmit,
        ?Collection $attempts = null,
    ): array {
        $attempts ??= $checkpoint->attempts()
            ->where('enrollment_id', $enrollment->id)
            ->get();
        $mastered = $attempts->contains(
            fn (LessonCheckpointAttempt $attempt): bool => $attempt->is_correct,
        );

        return [
            ...$this->publicPayload($checkpoint),
            'explanation' => $mastered ? $checkpoint->explanation : null,
            'interactive' => true,
            'can_submit' => $canSubmit,
            'mastered' => $mastered,
            'attempt_count' => $attempts->count(),
            'submit_url' => $submitUrl,
        ];
    }

    /** @return array<string, mixed> */
    public function publicPayload(LessonCheckpoint $checkpoint): array
    {
        return [
            'id' => $checkpoint->id,
            'type' => $checkpoint->checkpoint_type->value,
            'type_label' => $checkpoint->checkpoint_type->label(),
            'prompt' => $checkpoint->prompt,
            'options' => $checkpoint->configuration['options'] ?? [],
        ];
    }

    /**
     * @param  string|bool|array<int, mixed>  $answer
     * @return array{correct: bool, mastered: bool, feedback: string, explanation: string|null, attempt_count: int}
     */
    public function submit(
        LessonCheckpoint $checkpoint,
        Enrollment $enrollment,
        string|bool|array $answer,
    ): array {
        $submittedAnswer = $this->normalizeSubmission($checkpoint->checkpoint_type, $answer);
        $correct = $this->evaluate($checkpoint, $submittedAnswer);

        $attempt = DB::transaction(function () use ($checkpoint, $enrollment, $submittedAnswer, $correct): LessonCheckpointAttempt {
            LessonCheckpoint::query()->lockForUpdate()->findOrFail($checkpoint->id);
            $attemptNumber = ((int) LessonCheckpointAttempt::query()
                ->where('lesson_checkpoint_id', $checkpoint->id)
                ->where('enrollment_id', $enrollment->id)
                ->max('attempt_number')) + 1;

            return LessonCheckpointAttempt::query()->create([
                'lesson_checkpoint_id' => $checkpoint->id,
                'enrollment_id' => $enrollment->id,
                'submitted_answer' => $submittedAnswer,
                'is_correct' => $correct,
                'attempt_number' => $attemptNumber,
            ]);
        });

        $mastered = $correct || LessonCheckpointAttempt::query()
            ->where('lesson_checkpoint_id', $checkpoint->id)
            ->where('enrollment_id', $enrollment->id)
            ->where('is_correct', true)
            ->exists();

        return [
            'correct' => $correct,
            'mastered' => $mastered,
            'feedback' => $this->feedbackFor($checkpoint, $correct),
            'explanation' => $checkpoint->explanation,
            'attempt_count' => $attempt->attempt_number,
        ];
    }

    /**
     * Authored feedback for this outcome, falling back to the shared default
     * so checkpoints saved before these fields existed keep responding.
     */
    public function feedbackFor(LessonCheckpoint $checkpoint, bool $correct): string
    {
        $authored = $correct ? $checkpoint->correct_feedback : $checkpoint->incorrect_feedback;

        if (is_string($authored) && trim($authored) !== '') {
            return trim($authored);
        }

        return $correct
            ? __(self::DEFAULT_CORRECT_FEEDBACK)
            : __(self::DEFAULT_INCORRECT_FEEDBACK);
    }

    /** @param list<int> $referencedIds */
    public function deleteUnreferenced(Lesson $lesson, array $referencedIds): int
    {
        $query = $lesson->checkpoints();

        if ($referencedIds !== []) {
            $query->whereNotIn('id', $referencedIds);
        }

        return $query->delete();
    }

    /**
     * @param  string|bool|array<int, mixed>  $answer
     * @return array<string, mixed>
     */
    private function normalizeSubmission(LessonCheckpointType $type, string|bool|array $answer): array
    {
        if ($type === LessonCheckpointType::MultipleChoice && is_string($answer)) {
            return ['option_id' => $answer];
        }

        if ($type === LessonCheckpointType::MultipleSelect && is_array($answer)) {
            return [
                'option_ids' => collect($answer)
                    ->map(fn (mixed $id): string => (string) $id)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
            ];
        }

        if ($type === LessonCheckpointType::TrueFalse && is_bool($answer)) {
            return ['value' => $answer];
        }

        if ($type === LessonCheckpointType::FillBlank && is_string($answer)) {
            return ['value' => trim($answer)];
        }

        throw new InvalidArgumentException('The checkpoint answer format does not match its type.');
    }

    /** @param array<string, mixed> $submittedAnswer */
    private function evaluate(LessonCheckpoint $checkpoint, array $submittedAnswer): bool
    {
        return match ($checkpoint->checkpoint_type) {
            LessonCheckpointType::MultipleChoice => in_array(
                $submittedAnswer['option_id'] ?? null,
                $checkpoint->answer_key['correct_option_ids'] ?? [],
                true,
            ),
            LessonCheckpointType::MultipleSelect => $this->sameOptionSet(
                $submittedAnswer['option_ids'] ?? [],
                $checkpoint->answer_key['correct_option_ids'] ?? [],
            ),
            LessonCheckpointType::TrueFalse => ($submittedAnswer['value'] ?? null)
                === ($checkpoint->answer_key['correct_boolean'] ?? null),
            LessonCheckpointType::FillBlank => $this->acceptedFillAnswer(
                $submittedAnswer['value'] ?? null,
                $checkpoint->answer_key['accepted_answers'] ?? [],
            ),
        };
    }

    /** @param mixed $submitted @param mixed $correct */
    private function sameOptionSet(mixed $submitted, mixed $correct): bool
    {
        if (! is_array($submitted) || ! is_array($correct)) {
            return false;
        }

        $submitted = collect($submitted)->map(fn (mixed $id): string => (string) $id)->unique()->sort()->values()->all();
        $correct = collect($correct)->map(fn (mixed $id): string => (string) $id)->unique()->sort()->values()->all();

        return $submitted === $correct;
    }

    /** @param mixed $submitted @param mixed $accepted */
    private function acceptedFillAnswer(mixed $submitted, mixed $accepted): bool
    {
        if (! is_string($submitted) || ! is_array($accepted)) {
            return false;
        }

        $normalized = mb_strtolower(trim($submitted));

        return collect($accepted)->contains(
            fn (mixed $answer): bool => is_string($answer)
                && mb_strtolower(trim($answer)) === $normalized,
        );
    }
}
