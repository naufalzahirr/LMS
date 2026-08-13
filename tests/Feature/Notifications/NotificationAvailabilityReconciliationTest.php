<?php

namespace Tests\Feature\Notifications;

use App\Enums\AssessmentFeedbackMode;
use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Models\Assessment;
use App\Models\Competency;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningClass;
use App\Models\User;
use App\Notifications\AssessmentAvailableNotification;
use App\Services\ClassAssessmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NotificationAvailabilityReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_future_opening_assignment_notifies_after_opening_time_and_repeated_runs_do_not_duplicate(): void
    {
        $context = $this->availabilityContext();
        $service = app(ClassAssessmentService::class);
        $assignment = $service->assign($context['class'], $context['assessment'], $this->assignmentData($context['assessment'], [
            'opens_at' => now()->addHour(),
        ]));

        $this->assertSame(0, $context['student']->notifications()->count());

        $this->travelTo(now()->addHours(2));
        Artisan::call('notifications:send-assessment-availability');

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentAvailableNotification::class)->count(),
        );

        Artisan::call('notifications:send-assessment-availability');
        Artisan::call('notifications:send-assessment-availability');

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentAvailableNotification::class)->count(),
        );
        $this->assertNotNull($assignment);
    }

    public function test_event_and_scheduler_do_not_double_notify_for_an_immediately_open_assignment(): void
    {
        $context = $this->availabilityContext();
        app(ClassAssessmentService::class)->assign(
            $context['class'],
            $context['assessment'],
            $this->assignmentData($context['assessment']),
        );

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentAvailableNotification::class)->count(),
        );

        Artisan::call('notifications:send-assessment-availability');

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentAvailableNotification::class)->count(),
        );
    }

    public function test_new_active_enrollment_receives_its_own_notification_without_duplicating_existing_ones(): void
    {
        $context = $this->availabilityContext();
        app(ClassAssessmentService::class)->assign(
            $context['class'],
            $context['assessment'],
            $this->assignmentData($context['assessment']),
        );

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentAvailableNotification::class)->count(),
        );

        $newStudent = User::factory()->create();
        $newStudent->assignRole('Student');
        Enrollment::factory()->for($context['class'])->for($newStudent, 'student')->create([
            'status' => EnrollmentStatus::Active,
        ]);

        Artisan::call('notifications:send-assessment-availability');

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentAvailableNotification::class)->count(),
        );
        $this->assertSame(
            1,
            $newStudent->notifications()->where('type', AssessmentAvailableNotification::class)->count(),
        );
    }

    public function test_withdrawn_enrollment_never_receives_an_availability_notification(): void
    {
        $context = $this->availabilityContext(EnrollmentStatus::Withdrawn);
        app(ClassAssessmentService::class)->assign(
            $context['class'],
            $context['assessment'],
            $this->assignmentData($context['assessment']),
        );

        Artisan::call('notifications:send-assessment-availability');

        $this->assertSame(0, $context['student']->notifications()->count());
    }

    public function test_closed_assignment_does_not_notify(): void
    {
        $context = $this->availabilityContext();
        app(ClassAssessmentService::class)->assign($context['class'], $context['assessment'], $this->assignmentData($context['assessment'], [
            'opens_at' => now()->subDays(2),
            'closes_at' => now()->subDay(),
        ]));

        Artisan::call('notifications:send-assessment-availability');

        $this->assertSame(0, $context['student']->notifications()->count());
    }

    public function test_manual_transition_from_inactive_to_active_notifies_eligible_students(): void
    {
        $context = $this->availabilityContext();
        $service = app(ClassAssessmentService::class);
        $assignment = $service->assign($context['class'], $context['assessment'], $this->assignmentData($context['assessment'], [
            'status' => ClassAssessmentStatus::Inactive,
        ]));

        $this->assertSame(0, $context['student']->notifications()->count());

        $service->setStatus($assignment, ClassAssessmentStatus::Active);

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentAvailableNotification::class)->count(),
        );
    }

    public function test_saving_an_already_available_assignment_without_a_meaningful_change_does_not_renotify(): void
    {
        $context = $this->availabilityContext();
        $service = app(ClassAssessmentService::class);
        $assignment = $service->assign($context['class'], $context['assessment'], $this->assignmentData($context['assessment']));

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentAvailableNotification::class)->count(),
        );

        $service->update($assignment, [
            'opens_at' => null,
            'closes_at' => null,
            'max_attempts' => $assignment->max_attempts + 1,
            'status' => ClassAssessmentStatus::Active,
            'feedback_mode' => AssessmentFeedbackMode::ScoreOnly,
        ]);

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentAvailableNotification::class)->count(),
        );
    }

    /** @return array{class: LearningClass, assessment: Assessment, student: User} */
    private function availabilityContext(EnrollmentStatus $enrollmentStatus = EnrollmentStatus::Active): array
    {
        $course = Course::factory()->create();
        $competency = Competency::factory()->for($course)->create();
        $learningClass = LearningClass::factory()->for($course)->create(['status' => LearningClassStatus::Active]);
        $assessment = Assessment::factory()->for($competency)->create([
            'status' => AssessmentStatus::Published,
            'purpose' => AssessmentPurpose::Formative,
        ]);
        $student = User::factory()->create();
        $student->assignRole('Student');
        Enrollment::factory()->for($learningClass)->for($student, 'student')->create([
            'status' => $enrollmentStatus,
        ]);

        return ['class' => $learningClass, 'assessment' => $assessment, 'student' => $student];
    }

    /** @return array<string, mixed> */
    private function assignmentData(Assessment $assessment, array $overrides = []): array
    {
        return array_merge([
            'assessment_id' => $assessment->id,
            'opens_at' => null,
            'closes_at' => null,
            'max_attempts' => 2,
            'status' => ClassAssessmentStatus::Active,
            'feedback_mode' => AssessmentFeedbackMode::AfterFinalAttempt,
        ], $overrides);
    }
}
