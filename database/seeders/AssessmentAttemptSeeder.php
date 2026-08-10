<?php

namespace Database\Seeders;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentFeedbackMode;
use App\Enums\AssessmentPurpose;
use App\Enums\ClassAssessmentStatus;
use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptQuestion;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\Question;
use App\Models\User;
use App\Services\AssessmentAnswerService;
use App\Services\AssessmentAttemptService;
use App\Services\AssessmentService;
use App\Services\ClassAssessmentService;
use Illuminate\Database\Seeder;

class AssessmentAttemptSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('Development assessment attempts were not seeded in production.');

            return;
        }

        $learningClass = LearningClass::query()->where('code', 'FE-BATCH-A')->firstOrFail();
        $studentOne = User::query()->where('email', 'student.one@mlc.test')->firstOrFail();
        $studentTwo = User::query()->where('email', 'student.two@mlc.test')->firstOrFail();
        $firstEnrollment = $this->enrollment($learningClass, $studentOne);
        $secondEnrollment = $this->enrollment($learningClass, $studentTwo);
        $objectiveAssignment = $this->objectiveAssignment($learningClass);
        $mixedAssignment = LearningClassAssessment::query()
            ->where('learning_class_id', $learningClass->id)
            ->whereHas('assessment', fn ($query) => $query->where('code', 'HTML-FORMATIVE-01'))
            ->firstOrFail();

        $this->seedAttempt($studentOne, $firstEnrollment, $objectiveAssignment, false);
        $this->seedAttempt($studentTwo, $secondEnrollment, $mixedAssignment, true);
    }

    private function objectiveAssignment(LearningClass $learningClass): LearningClassAssessment
    {
        $assessment = Assessment::query()->where('code', 'HTML-OBJECTIVE-01')->first();
        $assessmentService = app(AssessmentService::class);

        if (! $assessment instanceof Assessment) {
            $competency = $learningClass->course->competencies()->where('code', 'HTML-01')->firstOrFail();
            $assessment = $assessmentService->create([
                'competency_id' => $competency->id,
                'title' => 'HTML Objective Practice',
                'code' => 'HTML-OBJECTIVE-01',
                'description' => 'Objective-only development sample with an immediately graded result.',
                'purpose' => AssessmentPurpose::Practice,
                'instructions' => 'Choose the best answer for each question.',
                'shuffle_questions' => false,
            ]);
            $questions = Question::query()
                ->where('competency_id', $competency->id)
                ->where('status', AcademicStatus::Active->value)
                ->whereIn('question_type', [
                    QuestionType::MultipleChoice->value,
                    QuestionType::MultipleSelect->value,
                    QuestionType::TrueFalse->value,
                    QuestionType::ShortAnswer->value,
                ])
                ->orderBy('sort_order')
                ->get();

            foreach ($questions as $question) {
                $assessmentService->addQuestion($assessment, $question);
            }

            $assessment = $assessmentService->publish($assessment);
        }

        $assignment = LearningClassAssessment::query()
            ->where('learning_class_id', $learningClass->id)
            ->where('assessment_id', $assessment->id)
            ->first();

        if ($assignment instanceof LearningClassAssessment) {
            return $assignment;
        }

        return app(ClassAssessmentService::class)->assign($learningClass, $assessment, [
            'assessment_id' => $assessment->id,
            'opens_at' => null,
            'closes_at' => null,
            'max_attempts' => 2,
            'status' => ClassAssessmentStatus::Active,
            'feedback_mode' => AssessmentFeedbackMode::AfterEachAttempt,
        ]);
    }

    private function seedAttempt(
        User $student,
        Enrollment $enrollment,
        LearningClassAssessment $assignment,
        bool $includeEssay,
    ): void {
        if (AssessmentAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('learning_class_assessment_id', $assignment->id)
            ->exists()) {
            return;
        }

        $attemptService = app(AssessmentAttemptService::class);
        $answerService = app(AssessmentAnswerService::class);
        $attempt = $attemptService->startAttempt($student, $enrollment, $assignment);
        $attempt->load('attemptQuestions.options', 'attemptQuestions.acceptedAnswers');

        foreach ($attempt->attemptQuestions as $question) {
            $answerService->save($student, $attempt, $question, $this->correctAnswer($question, $includeEssay));
        }

        $attemptService->submit($student, $attempt);
    }

    /** @return array{answer_text: string|null, answer_boolean: bool|null, selected_option_ids: array<int, int>} */
    private function correctAnswer(AssessmentAttemptQuestion $question, bool $includeEssay): array
    {
        return match ($question->question_type) {
            QuestionType::MultipleChoice, QuestionType::MultipleSelect => [
                'answer_text' => null,
                'answer_boolean' => null,
                'selected_option_ids' => $question->options->where('is_correct', true)->pluck('id')->all(),
            ],
            QuestionType::TrueFalse => [
                'answer_text' => null,
                'answer_boolean' => $question->correct_boolean,
                'selected_option_ids' => [],
            ],
            QuestionType::ShortAnswer => [
                'answer_text' => $question->acceptedAnswers->first()?->answer_text,
                'answer_boolean' => null,
                'selected_option_ids' => [],
            ],
            QuestionType::Essay => [
                'answer_text' => $includeEssay
                    ? 'Semantic HTML gives assistive technology meaningful structure and landmarks.'
                    : null,
                'answer_boolean' => null,
                'selected_option_ids' => [],
            ],
        };
    }

    private function enrollment(LearningClass $learningClass, User $student): Enrollment
    {
        return Enrollment::query()
            ->where('learning_class_id', $learningClass->id)
            ->where('student_id', $student->id)
            ->firstOrFail();
    }
}
