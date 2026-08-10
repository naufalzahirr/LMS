<?php

namespace Database\Seeders;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentFeedbackMode;
use App\Enums\AssessmentPurpose;
use App\Enums\ClassAssessmentStatus;
use App\Enums\MasteryRuleStatus;
use App\Enums\QuestionType;
use App\Enums\StudentCompetencyStatus;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptQuestion;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\StudentCompetencyProgress;
use App\Models\User;
use App\Services\AssessmentAnswerService;
use App\Services\AssessmentAttemptService;
use App\Services\AssessmentService;
use App\Services\ClassAssessmentService;
use App\Services\CompetencyPrerequisiteService;
use App\Services\MasteryRuleService;
use Illuminate\Database\Seeder;

class MasteryLearningSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('Development mastery-learning samples were not seeded in production.');

            return;
        }

        $learningClass = LearningClass::query()->where('code', 'FE-BATCH-A')->firstOrFail();
        $competency = Competency::query()->where('code', 'HTML-01')->firstOrFail();
        $css = Competency::query()->where('code', 'CSS-01')->firstOrFail();

        if (! $css->prerequisites()->whereKey($competency->id)->exists()) {
            app(CompetencyPrerequisiteService::class)->add($css, $competency);
        }

        $assessment = $this->assessment($competency);
        $assignment = $this->assignment($learningClass, $assessment);
        $lessons = Lesson::query()
            ->whereHas('module', fn ($query) => $query->where('competency_id', $competency->id))
            ->where('status', AcademicStatus::Active->value)
            ->orderBy('module_id')
            ->orderBy('sort_order')
            ->limit(2)
            ->pluck('id')
            ->all();
        app(MasteryRuleService::class)->save($learningClass, $competency, [
            'learning_class_assessment_id' => $assignment->id,
            'mastery_score' => '80.00',
            'require_remedial' => true,
            'status' => MasteryRuleStatus::Active,
            'remedial_lesson_ids' => $lessons,
        ]);

        $this->attempt($learningClass, $competency, $assignment, 'student.one@mlc.test', true);
        $this->attempt($learningClass, $competency, $assignment, 'student.two@mlc.test', false);
    }

    private function assessment(Competency $competency): Assessment
    {
        $assessment = Assessment::query()->where('code', 'HTML-MASTERY-01')->first();

        if ($assessment instanceof Assessment) {
            return $assessment;
        }

        $service = app(AssessmentService::class);
        $assessment = $service->create([
            'competency_id' => $competency->id,
            'title' => 'HTML Fundamentals Mastery Check',
            'code' => 'HTML-MASTERY-01',
            'description' => 'Designated mastery assessment for the development class.',
            'purpose' => AssessmentPurpose::Mastery,
            'instructions' => 'Reach at least 80% to master HTML Fundamentals.',
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
            $service->addQuestion($assessment, $question);
        }

        return $service->publish($assessment);
    }

    private function assignment(LearningClass $learningClass, Assessment $assessment): LearningClassAssessment
    {
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

    private function attempt(
        LearningClass $learningClass,
        Competency $competency,
        LearningClassAssessment $assignment,
        string $email,
        bool $correct,
    ): void {
        $student = User::query()->where('email', $email)->firstOrFail();
        $enrollment = Enrollment::query()
            ->where('learning_class_id', $learningClass->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        if (AssessmentAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('learning_class_assessment_id', $assignment->id)
            ->exists()) {
            return;
        }

        StudentCompetencyProgress::query()->updateOrCreate(
            ['enrollment_id' => $enrollment->id, 'competency_id' => $competency->id],
            ['status' => StudentCompetencyStatus::ReadyForAssessment, 'started_at' => now()],
        );
        $attempts = app(AssessmentAttemptService::class);
        $attempt = $attempts->startAttempt($student, $enrollment, $assignment);
        $attempt->load('attemptQuestions.options', 'attemptQuestions.acceptedAnswers');

        foreach ($attempt->attemptQuestions as $question) {
            app(AssessmentAnswerService::class)->save(
                $student,
                $attempt,
                $question,
                $this->answer($question, $correct),
            );
        }

        $attempts->submit($student, $attempt);
    }

    /** @return array{answer_text: string|null, answer_boolean: bool|null, selected_option_ids: array<int, int>} */
    private function answer(AssessmentAttemptQuestion $question, bool $correct): array
    {
        return match ($question->question_type) {
            QuestionType::MultipleChoice => [
                'answer_text' => null,
                'answer_boolean' => null,
                'selected_option_ids' => $question->options
                    ->where('is_correct', $correct)->take(1)->pluck('id')->all(),
            ],
            QuestionType::MultipleSelect => [
                'answer_text' => null,
                'answer_boolean' => null,
                'selected_option_ids' => $correct
                    ? $question->options->where('is_correct', true)->pluck('id')->all()
                    : [],
            ],
            QuestionType::TrueFalse => [
                'answer_text' => null,
                'answer_boolean' => $correct ? $question->correct_boolean : ! $question->correct_boolean,
                'selected_option_ids' => [],
            ],
            QuestionType::ShortAnswer => [
                'answer_text' => $correct ? $question->acceptedAnswers->first()?->answer_text : 'incorrect',
                'answer_boolean' => null,
                'selected_option_ids' => [],
            ],
            QuestionType::Essay => [
                'answer_text' => null,
                'answer_boolean' => null,
                'selected_option_ids' => [],
            ],
        };
    }
}
