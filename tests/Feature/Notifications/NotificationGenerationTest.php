<?php

namespace Tests\Feature\Notifications;

use App\Enums\AssessmentFeedbackMode;
use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\MasteryRule;
use App\Models\ParentStudentRelationship;
use App\Models\RemedialAssignment;
use App\Models\StudentCompetencyProgress;
use App\Models\User;
use App\Notifications\AssessmentGradedNotification;
use App\Notifications\AssessmentNeedsGradingNotification;
use App\Notifications\ChildAssessmentGradedNotification;
use App\Notifications\ChildRemedialAssignedNotification;
use App\Notifications\RemedialAssignedNotification;
use App\Notifications\StudentNeedsRemedialNotification;
use App\Services\AssessmentAnswerService;
use App\Services\AssessmentAttemptService;
use App\Services\AssessmentGradingService;
use App\Services\ClassAssessmentService;
use App\Services\RemedialAssignmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAssessmentAttemptContexts;
use Tests\TestCase;

class NotificationGenerationTest extends TestCase
{
    use BuildsAssessmentAttemptContexts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_tutor_needs_grading_notification_fires_for_authorized_tutor_only(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::Essay]);
        $tutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($tutor);
        $unrelatedTutor = $this->userWithAssessmentRole('Tutor');

        $this->submitPendingAttempt($context);

        $this->assertSame(
            1,
            $tutor->notifications()->where('type', AssessmentNeedsGradingNotification::class)->count(),
        );
        $this->assertSame(0, $unrelatedTutor->notifications()->count());
    }

    public function test_auto_graded_attempt_does_not_notify_tutor_for_grading(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $tutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($tutor);

        $this->submitCorrectObjectiveAttempt($context);

        $this->assertSame(
            0,
            $tutor->notifications()->where('type', AssessmentNeedsGradingNotification::class)->count(),
        );
    }

    public function test_student_and_linked_parent_notified_when_auto_graded(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $parent = $this->userWithAssessmentRole('Parent');
        ParentStudentRelationship::factory()->create([
            'parent_id' => $parent->id,
            'student_id' => $context['student']->id,
        ]);
        $unrelatedParent = $this->userWithAssessmentRole('Parent');

        $this->submitCorrectObjectiveAttempt($context);

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentGradedNotification::class)->count(),
        );
        $this->assertSame(
            1,
            $parent->notifications()->where('type', ChildAssessmentGradedNotification::class)->count(),
        );
        $this->assertSame(0, $unrelatedParent->notifications()->count());
    }

    public function test_graded_attempt_never_sends_the_student_facing_notification_to_a_tutor(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::Essay]);
        $authorizedTutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($authorizedTutor);
        $unrelatedTutor = $this->userWithAssessmentRole('Tutor');
        $attempt = $this->submitPendingAttempt($context);
        $essay = $attempt->attemptQuestions()->where('question_type', QuestionType::Essay->value)->firstOrFail();

        // The authorized Tutor's own grading-needed notification is a
        // separate, correctly-scoped notification type — confirm it still
        // fires, distinctly from the Student-facing graded notification below.
        $this->assertSame(
            1,
            $authorizedTutor->notifications()->where('type', AssessmentNeedsGradingNotification::class)->count(),
        );

        app(AssessmentGradingService::class)->grade($attempt, $authorizedTutor, [
            $this->essayGrade($essay->id, '4.00'),
        ]);

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentGradedNotification::class)->count(),
        );
        $this->assertSame(
            0,
            $authorizedTutor->notifications()->where('type', AssessmentGradedNotification::class)->count(),
        );
        $this->assertSame(
            0,
            $unrelatedTutor->notifications()->where('type', AssessmentGradedNotification::class)->count(),
        );
    }

    public function test_partial_essay_grading_does_not_notify_but_full_grading_does(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::Essay, QuestionType::Essay]);
        $attempt = $this->submitPendingAttempt($context);
        $essays = $attempt->attemptQuestions()->where('question_type', QuestionType::Essay->value)->get();
        $grader = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($grader);

        app(AssessmentGradingService::class)->grade($attempt, $grader, [
            $this->essayGrade($essays[0]->id, '3.00'),
        ]);

        $this->assertSame(
            0,
            $context['student']->notifications()->where('type', AssessmentGradedNotification::class)->count(),
        );

        app(AssessmentGradingService::class)->grade($attempt, $grader, [
            $this->essayGrade($essays[1]->id, '3.00'),
        ]);

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentGradedNotification::class)->count(),
        );
    }

    public function test_regrading_an_already_graded_attempt_does_not_duplicate_student_or_parent_notifications(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::Essay]);
        $parent = $this->userWithAssessmentRole('Parent');
        ParentStudentRelationship::factory()->create([
            'parent_id' => $parent->id,
            'student_id' => $context['student']->id,
        ]);
        $unrelatedParent = $this->userWithAssessmentRole('Parent');
        $attempt = $this->submitPendingAttempt($context);
        $essay = $attempt->attemptQuestions()->where('question_type', QuestionType::Essay->value)->firstOrFail();
        $grader = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($grader);

        app(AssessmentGradingService::class)->grade($attempt, $grader, [
            $this->essayGrade($essay->id, '3.00'),
        ]);

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentGradedNotification::class)->count(),
        );
        $this->assertSame(
            1,
            $parent->notifications()->where('type', ChildAssessmentGradedNotification::class)->count(),
        );
        $this->assertSame(0, $unrelatedParent->notifications()->count());

        // Re-grading an attempt already in the Graded state (e.g. a Tutor
        // correcting a score) must not send a second "graded" notification.
        app(AssessmentGradingService::class)->grade($attempt, $grader, [
            $this->essayGrade($essay->id, '4.00'),
        ]);

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentGradedNotification::class)->count(),
        );
        $this->assertSame(
            1,
            $parent->notifications()->where('type', ChildAssessmentGradedNotification::class)->count(),
        );
        $this->assertSame(0, $unrelatedParent->notifications()->count());
    }

    public function test_remedial_assignment_notifies_student_tutor_and_parent_but_not_unrelated_users(): void
    {
        $context = $this->masteryContext();
        $tutor = $this->userWithAssessmentRole('Tutor');
        $context['class']->tutors()->attach($tutor);
        $parent = $this->userWithAssessmentRole('Parent');
        ParentStudentRelationship::factory()->create([
            'parent_id' => $parent->id,
            'student_id' => $context['student']->id,
        ]);
        $unrelatedTutor = $this->userWithAssessmentRole('Tutor');
        $unrelatedParent = $this->userWithAssessmentRole('Parent');

        $this->submitMultipleChoice($context, false);

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', RemedialAssignedNotification::class)->count(),
        );
        $this->assertSame(
            1,
            $tutor->notifications()->where('type', StudentNeedsRemedialNotification::class)->count(),
        );
        $this->assertSame(
            1,
            $parent->notifications()->where('type', ChildRemedialAssignedNotification::class)->count(),
        );
        $this->assertSame(0, $unrelatedTutor->notifications()->count());
        $this->assertSame(0, $unrelatedParent->notifications()->count());
    }

    public function test_remedial_notification_does_not_duplicate_for_an_already_open_assignment(): void
    {
        $context = $this->masteryContext();
        $failedAttempt = $this->submitMultipleChoice($context, false);

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', RemedialAssignedNotification::class)->count(),
        );

        // Re-invoking the same idempotent creation path (e.g. a retried evaluation
        // touching the same open assignment) must not send a second notification.
        app(RemedialAssignmentService::class)->createForFailure($context['rule'], $failedAttempt);

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', RemedialAssignedNotification::class)->count(),
        );
        $this->assertSame(1, RemedialAssignment::query()->count());
    }

    public function test_assessment_available_notification_fires_for_active_enrollments_when_immediately_open(): void
    {
        $availabilityContext = $this->availabilityContext();
        $student = $availabilityContext['student'];

        app(ClassAssessmentService::class)->assign(
            $availabilityContext['class'],
            $availabilityContext['assessment'],
            $this->availableAssignmentData($availabilityContext['assessment']),
        );

        $this->assertSame(1, $student->notifications()->count());
    }

    public function test_assessment_available_notification_does_not_fire_for_a_future_opens_at(): void
    {
        $availabilityContext = $this->availabilityContext();
        $student = $availabilityContext['student'];

        app(ClassAssessmentService::class)->assign(
            $availabilityContext['class'],
            $availabilityContext['assessment'],
            [
                ...$this->availableAssignmentData($availabilityContext['assessment']),
                'opens_at' => now()->addDay(),
            ],
        );

        $this->assertSame(0, $student->notifications()->count());
    }

    public function test_assessment_available_notification_excludes_withdrawn_enrollments(): void
    {
        $availabilityContext = $this->availabilityContext();
        $withdrawnStudent = $this->userWithAssessmentRole('Student');
        Enrollment::factory()->for($availabilityContext['class'])->for($withdrawnStudent, 'student')->create([
            'status' => EnrollmentStatus::Withdrawn,
        ]);

        app(ClassAssessmentService::class)->assign(
            $availabilityContext['class'],
            $availabilityContext['assessment'],
            $this->availableAssignmentData($availabilityContext['assessment']),
        );

        $this->assertSame(0, $withdrawnStudent->notifications()->count());
        $this->assertSame(1, $availabilityContext['student']->notifications()->count());
    }

    /** @param array<string, mixed> $context */
    private function submitPendingAttempt(array $context): AssessmentAttempt
    {
        $attemptService = app(AssessmentAttemptService::class);
        $answerService = app(AssessmentAnswerService::class);
        $attempt = $attemptService->startAttempt($context['student'], $context['enrollment'], $context['assignment']);
        $attempt->load('attemptQuestions.options');

        foreach ($attempt->attemptQuestions as $question) {
            if ($question->question_type === QuestionType::Essay) {
                $answerService->save($context['student'], $attempt, $question, [
                    'answer_text' => 'Essay response',
                    'answer_boolean' => null,
                    'selected_option_ids' => [],
                ]);
            }
        }

        return $attemptService->submit($context['student'], $attempt);
    }

    /** @param array<string, mixed> $context */
    private function submitCorrectObjectiveAttempt(array $context): AssessmentAttempt
    {
        $attemptService = app(AssessmentAttemptService::class);
        $answerService = app(AssessmentAnswerService::class);
        $attempt = $attemptService->startAttempt($context['student'], $context['enrollment'], $context['assignment']);
        $question = $attempt->attemptQuestions()->with('options')->firstOrFail();
        $answerService->save($context['student'], $attempt, $question, [
            'answer_text' => null,
            'answer_boolean' => null,
            'selected_option_ids' => [$question->options->firstWhere('is_correct', true)->id],
        ]);

        return $attemptService->submit($context['student'], $attempt);
    }

    /** @return array<string, mixed> */
    private function masteryContext(): array
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice]);
        $competency = $context['assessment']->competency;
        $context['assessment']->update(['purpose' => AssessmentPurpose::Mastery]);
        $rule = MasteryRule::factory()->create([
            'learning_class_id' => $context['class']->id,
            'competency_id' => $competency->id,
            'learning_class_assessment_id' => $context['assignment']->id,
            'mastery_score' => '80.00',
            'require_remedial' => true,
        ]);
        StudentCompetencyProgress::factory()->ready()->create([
            'enrollment_id' => $context['enrollment']->id,
            'competency_id' => $competency->id,
        ]);

        return $context + compact('competency', 'rule');
    }

    /** @param array<string, mixed> $context */
    private function submitMultipleChoice(array $context, bool $correct): AssessmentAttempt
    {
        $attempts = app(AssessmentAttemptService::class);
        $attempt = $attempts->startAttempt($context['student'], $context['enrollment'], $context['assignment']);
        $question = $attempt->attemptQuestions()->with('options')->firstOrFail();
        $option = $question->options->firstWhere('is_correct', $correct);
        app(AssessmentAnswerService::class)->save($context['student'], $attempt, $question, [
            'answer_text' => null,
            'answer_boolean' => null,
            'selected_option_ids' => [$option->id],
        ]);

        return $attempts->submit($context['student'], $attempt);
    }

    /** @return array{attempt_question_id: int, manual_score: string, feedback: string|null} */
    private function essayGrade(int $attemptQuestionId, string $score): array
    {
        return [
            'attempt_question_id' => $attemptQuestionId,
            'manual_score' => $score,
            'feedback' => 'Written feedback.',
        ];
    }

    /** @return array{class: LearningClass, assessment: Assessment, student: User} */
    private function availabilityContext(): array
    {
        $course = Course::factory()->create();
        $competency = Competency::factory()->for($course)->create();
        $learningClass = LearningClass::factory()->for($course)->create(['status' => LearningClassStatus::Active]);
        $assessment = Assessment::factory()->for($competency)->create([
            'status' => AssessmentStatus::Published,
            'purpose' => AssessmentPurpose::Formative,
        ]);
        $student = $this->userWithAssessmentRole('Student');
        Enrollment::factory()->for($learningClass)->for($student, 'student')->create([
            'status' => EnrollmentStatus::Active,
        ]);

        return ['class' => $learningClass, 'assessment' => $assessment, 'student' => $student];
    }

    /** @return array<string, mixed> */
    private function availableAssignmentData(Assessment $assessment): array
    {
        return [
            'assessment_id' => $assessment->id,
            'opens_at' => null,
            'closes_at' => null,
            'max_attempts' => 2,
            'status' => ClassAssessmentStatus::Active,
            'feedback_mode' => AssessmentFeedbackMode::AfterFinalAttempt,
        ];
    }
}
