<?php

namespace Tests\Concerns;

use App\Enums\AssessmentFeedbackMode;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\LearningClassAssessment;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;

trait BuildsAssessmentAttemptContexts
{
    /**
     * @param  array<int, QuestionType>  $types
     * @param  array<string, mixed>  $assignmentOverrides
     * @return array{student: User, enrollment: Enrollment, class: LearningClass, assignment: LearningClassAssessment, assessment: Assessment, questions: array<string, Question>}
     */
    protected function makeAssessmentContext(
        array $types = [],
        array $assignmentOverrides = [],
    ): array {
        $types = $types === [] ? QuestionType::cases() : $types;
        $course = Course::factory()->create();
        $competency = Competency::factory()->for($course)->create();
        $bank = QuestionBank::factory()->for($course)->create();
        $learningClass = LearningClass::factory()->for($course)->create();
        $student = $this->userWithAssessmentRole('Student');
        $enrollment = Enrollment::factory()
            ->for($learningClass)
            ->for($student, 'student')
            ->create(['status' => EnrollmentStatus::Active]);
        $assessment = Assessment::factory()->for($competency)->create([
            'status' => AssessmentStatus::Published,
            'shuffle_questions' => false,
        ]);
        $questions = [];

        foreach ($types as $index => $type) {
            $factory = Question::factory()->for($bank)->for($competency);
            $question = match ($type) {
                QuestionType::MultipleChoice => $factory->multipleChoice()->create(),
                QuestionType::MultipleSelect => $factory->multipleSelect()->create(),
                QuestionType::TrueFalse => $factory->trueFalse()->create(),
                QuestionType::ShortAnswer => $factory->shortAnswer()->create(),
                QuestionType::Essay => $factory->essay()->create(),
            };

            if ($type === QuestionType::ShortAnswer) {
                $question->acceptedAnswers()->delete();
                $question->acceptedAnswers()->createMany([
                    ['answer_text' => 'Laravel', 'case_sensitive' => false],
                    ['answer_text' => 'ExactCase', 'case_sensitive' => true],
                ]);
            }

            $points = match ($type) {
                QuestionType::MultipleChoice => '2.00',
                QuestionType::MultipleSelect => '3.00',
                QuestionType::TrueFalse => '1.00',
                QuestionType::ShortAnswer => '2.00',
                QuestionType::Essay => '4.00',
            };
            $assessment->assessmentQuestions()->create([
                'question_id' => $question->id,
                'points' => $points,
                'sort_order' => $index,
            ]);
            $questions[$type->value] = $question;
        }

        $assignment = LearningClassAssessment::factory()
            ->for($learningClass)
            ->for($assessment)
            ->create(array_merge([
                'status' => ClassAssessmentStatus::Active,
                'feedback_mode' => AssessmentFeedbackMode::AfterFinalAttempt,
                'max_attempts' => 3,
            ], $assignmentOverrides));

        return compact('student', 'enrollment', 'assignment', 'assessment', 'questions') + [
            'class' => $learningClass,
        ];
    }

    protected function userWithAssessmentRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
