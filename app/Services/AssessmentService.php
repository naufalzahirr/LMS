<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\Competency;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentService
{
    public function __construct(private readonly QuestionService $questionService) {}

    /** @param array{competency_id: int, title: string, code: string|null, description: string|null, purpose: AssessmentPurpose, instructions: string|null, shuffle_questions: bool} $data */
    public function create(array $data): Assessment
    {
        return DB::transaction(fn (): Assessment => Assessment::query()->create([
            ...$data,
            'status' => AssessmentStatus::Draft,
        ]));
    }

    /** @param array{competency_id: int, title: string, code: string|null, description: string|null, purpose: AssessmentPurpose, instructions: string|null, shuffle_questions: bool} $data */
    public function update(Assessment $assessment, array $data): Assessment
    {
        return DB::transaction(function () use ($assessment, $data): Assessment {
            $this->ensureEditable($assessment);
            $this->ensureUnused($assessment);

            if ($assessment->assessmentQuestions()->exists() && $assessment->competency_id !== $data['competency_id']) {
                throw ValidationException::withMessages([
                    'competency_id' => __('An assessment with questions cannot move to another competency.'),
                ]);
            }

            $assessment->update($data);

            return $assessment->refresh();
        });
    }

    public function publish(Assessment $assessment): Assessment
    {
        return DB::transaction(function () use ($assessment): Assessment {
            $this->ensureEditable($assessment);
            $activeCompetency = Competency::query()
                ->whereKey($assessment->competency_id)
                ->where('status', AcademicStatus::Active->value)
                ->whereHas('course', fn ($query) => $query
                    ->where('status', AcademicStatus::Active->value)
                    ->whereHas('program', fn ($query) => $query->where('status', AcademicStatus::Active->value)))
                ->exists();

            if (! $activeCompetency) {
                throw ValidationException::withMessages([
                    'assessment' => __('The competency, course, and program must be active before publishing.'),
                ]);
            }

            $questionIds = $assessment->assessmentQuestions()->pluck('question_id');

            if ($questionIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'assessment' => __('Add at least one question before publishing.'),
                ]);
            }

            $questions = Question::query()->whereIn('id', $questionIds)
                ->where('status', AcademicStatus::Active->value)
                ->with(['options', 'acceptedAnswers'])
                ->get();

            if ($questions->count() !== $questionIds->count()
                || $questions->contains(fn (Question $question): bool => ! $this->questionService->hasValidAnswerKey($question))) {
                throw ValidationException::withMessages([
                    'assessment' => __('Every assessment question must be active and have a valid answer key.'),
                ]);
            }

            $assessment->update(['status' => AssessmentStatus::Published]);

            return $assessment->refresh();
        });
    }

    public function archive(Assessment $assessment): Assessment
    {
        return DB::transaction(function () use ($assessment): Assessment {
            if ($assessment->status === AssessmentStatus::Archived) {
                return $assessment;
            }

            $assessment->update(['status' => AssessmentStatus::Archived]);

            return $assessment->refresh();
        });
    }

    public function delete(Assessment $assessment): void
    {
        DB::transaction(function () use ($assessment): void {
            $this->ensureUnused($assessment);

            if ($assessment->status !== AssessmentStatus::Draft) {
                throw ValidationException::withMessages([
                    'assessment' => __('Published or archived assessments cannot be deleted; archive them instead.'),
                ]);
            }

            if ($assessment->classAssignments()->exists()) {
                throw ValidationException::withMessages([
                    'assessment' => __('This assessment cannot be deleted while assigned to a class.'),
                ]);
            }

            $assessment->delete();
        });
    }

    public function addQuestion(Assessment $assessment, Question $question, ?string $points = null): AssessmentQuestion
    {
        return DB::transaction(function () use ($assessment, $question, $points): AssessmentQuestion {
            $this->ensureEditable($assessment);
            $this->ensureUnused($assessment);

            if ($question->competency_id !== $assessment->competency_id) {
                throw ValidationException::withMessages([
                    'question_id' => __('Only questions from the assessment competency may be added.'),
                ]);
            }

            if ($question->status !== AcademicStatus::Active) {
                throw ValidationException::withMessages(['question_id' => __('Only active questions may be added.')]);
            }

            if ($assessment->assessmentQuestions()->where('question_id', $question->id)->exists()) {
                throw ValidationException::withMessages(['question_id' => __('This question is already in the assessment.')]);
            }

            $resolvedPoints = $points ?? $question->default_points;

            if ((float) $resolvedPoints <= 0) {
                throw ValidationException::withMessages(['points' => __('Points must be greater than zero.')]);
            }

            $nextOrder = ((int) $assessment->assessmentQuestions()->max('sort_order')) + 1;

            return $assessment->assessmentQuestions()->create([
                'question_id' => $question->id,
                'points' => $resolvedPoints,
                'sort_order' => $nextOrder,
            ]);
        });
    }

    public function removeQuestion(Assessment $assessment, AssessmentQuestion $assessmentQuestion): void
    {
        DB::transaction(function () use ($assessment, $assessmentQuestion): void {
            $this->ensureEditable($assessment);
            $this->ensureUnused($assessment);
            $this->ensureCompositionBelongsToAssessment($assessment, $assessmentQuestion);
            $assessmentQuestion->delete();
            $this->normalizeOrdering($assessment);
        });
    }

    public function updateQuestionPoints(Assessment $assessment, AssessmentQuestion $assessmentQuestion, string $points): AssessmentQuestion
    {
        return DB::transaction(function () use ($assessment, $assessmentQuestion, $points): AssessmentQuestion {
            $this->ensureEditable($assessment);
            $this->ensureUnused($assessment);
            $this->ensureCompositionBelongsToAssessment($assessment, $assessmentQuestion);

            if ((float) $points <= 0) {
                throw ValidationException::withMessages(['points' => __('Points must be greater than zero.')]);
            }

            $assessmentQuestion->update(['points' => $points]);

            return $assessmentQuestion->refresh();
        });
    }

    public function moveQuestion(Assessment $assessment, AssessmentQuestion $assessmentQuestion, string $direction): void
    {
        DB::transaction(function () use ($assessment, $assessmentQuestion, $direction): void {
            $this->ensureEditable($assessment);
            $this->ensureUnused($assessment);
            $this->ensureCompositionBelongsToAssessment($assessment, $assessmentQuestion);
            $ordered = $assessment->assessmentQuestions()->get();
            $index = $ordered->search(fn (AssessmentQuestion $item): bool => $item->is($assessmentQuestion));
            $targetIndex = $direction === 'up' ? (int) $index - 1 : (int) $index + 1;

            if ($index === false || ! $ordered->has($targetIndex)) {
                return;
            }

            $target = $ordered->get($targetIndex);
            $currentOrder = $assessmentQuestion->sort_order;
            $assessmentQuestion->update(['sort_order' => $target->sort_order]);
            $target->update(['sort_order' => $currentOrder]);
            $this->normalizeOrdering($assessment);
        });
    }

    private function ensureEditable(Assessment $assessment): void
    {
        if ($assessment->status === AssessmentStatus::Archived) {
            throw ValidationException::withMessages(['assessment' => __('Archived assessments are read-only.')]);
        }
    }

    private function ensureUnused(Assessment $assessment): void
    {
        if ($assessment->classAssignments()->whereHas('attempts')->exists()) {
            throw ValidationException::withMessages([
                'assessment' => __('This assessment has already been used by students. Create a new assessment/version for structural changes.'),
            ]);
        }
    }

    private function ensureCompositionBelongsToAssessment(Assessment $assessment, AssessmentQuestion $item): void
    {
        if ($item->assessment_id !== $assessment->id) {
            throw ValidationException::withMessages(['assessment' => __('The assessment question does not belong to this assessment.')]);
        }
    }

    private function normalizeOrdering(Assessment $assessment): void
    {
        foreach ($assessment->assessmentQuestions()->get() as $index => $item) {
            if ($item->sort_order !== $index) {
                $item->update(['sort_order' => $index]);
            }
        }
    }
}
