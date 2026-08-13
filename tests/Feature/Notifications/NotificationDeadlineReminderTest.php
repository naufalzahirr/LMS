<?php

namespace Tests\Feature\Notifications;

use App\Enums\QuestionType;
use App\Notifications\AssessmentDeadlineReminderNotification;
use App\Services\AssessmentAnswerService;
use App\Services\AssessmentAttemptService;
use App\Services\ClassAssessmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\BuildsAssessmentAttemptContexts;
use Tests\TestCase;

class NotificationDeadlineReminderTest extends TestCase
{
    use BuildsAssessmentAttemptContexts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_eligible_student_receives_a_deadline_reminder(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice], [
            'closes_at' => now()->addHours(5),
        ]);

        Artisan::call('notifications:send-deadline-reminders');

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentDeadlineReminderNotification::class)->count(),
        );
    }

    public function test_running_the_command_twice_does_not_duplicate_the_reminder(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice], [
            'closes_at' => now()->addHours(5),
        ]);

        Artisan::call('notifications:send-deadline-reminders');
        Artisan::call('notifications:send-deadline-reminders');
        Artisan::call('notifications:send-deadline-reminders');

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentDeadlineReminderNotification::class)->count(),
        );
    }

    public function test_student_who_has_exhausted_attempts_is_not_reminded(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice], [
            'closes_at' => now()->addHours(5),
            'max_attempts' => 1,
        ]);
        $attemptService = app(AssessmentAttemptService::class);
        $attempt = $attemptService->startAttempt($context['student'], $context['enrollment'], $context['assignment']);
        $question = $attempt->attemptQuestions()->with('options')->firstOrFail();
        app(AssessmentAnswerService::class)->save($context['student'], $attempt, $question, [
            'answer_text' => null,
            'answer_boolean' => null,
            'selected_option_ids' => [$question->options->first()->id],
        ]);
        $attemptService->submit($context['student'], $attempt);

        Artisan::call('notifications:send-deadline-reminders');

        $this->assertSame(
            0,
            $context['student']->notifications()->where('type', AssessmentDeadlineReminderNotification::class)->count(),
        );
    }

    public function test_assignment_without_a_deadline_is_not_reminded(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice], [
            'closes_at' => null,
        ]);

        Artisan::call('notifications:send-deadline-reminders');

        $this->assertSame(0, $context['student']->notifications()->count());
    }

    public function test_assignment_closing_outside_the_reminder_window_is_not_reminded(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice], [
            'closes_at' => now()->addHours(48),
        ]);

        Artisan::call('notifications:send-deadline-reminders');

        $this->assertSame(0, $context['student']->notifications()->count());
    }

    public function test_rescheduling_the_deadline_to_a_new_occurrence_produces_a_second_legitimate_reminder(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice], [
            'closes_at' => now()->addHours(5),
        ]);

        Artisan::call('notifications:send-deadline-reminders');

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentDeadlineReminderNotification::class)->count(),
        );

        app(ClassAssessmentService::class)->update($context['assignment'], [
            'opens_at' => $context['assignment']->opens_at,
            'closes_at' => now()->addDays(10),
            'max_attempts' => $context['assignment']->max_attempts,
            'status' => $context['assignment']->status,
            'feedback_mode' => $context['assignment']->feedback_mode,
        ]);

        Artisan::call('notifications:send-deadline-reminders');
        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentDeadlineReminderNotification::class)->count(),
        );

        $this->travelTo(now()->addDays(9)->addHours(20));
        Artisan::call('notifications:send-deadline-reminders');
        Artisan::call('notifications:send-deadline-reminders');

        $this->assertSame(
            2,
            $context['student']->notifications()->where('type', AssessmentDeadlineReminderNotification::class)->count(),
        );
    }

    public function test_updating_the_assignment_without_changing_the_deadline_still_reminds_once(): void
    {
        $context = $this->makeAssessmentContext([QuestionType::MultipleChoice], [
            'closes_at' => now()->addHours(5),
        ]);

        Artisan::call('notifications:send-deadline-reminders');

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentDeadlineReminderNotification::class)->count(),
        );

        app(ClassAssessmentService::class)->update($context['assignment'], [
            'opens_at' => $context['assignment']->opens_at,
            'closes_at' => $context['assignment']->closes_at,
            'max_attempts' => $context['assignment']->max_attempts + 1,
            'status' => $context['assignment']->status,
            'feedback_mode' => $context['assignment']->feedback_mode,
        ]);

        Artisan::call('notifications:send-deadline-reminders');

        $this->assertSame(
            1,
            $context['student']->notifications()->where('type', AssessmentDeadlineReminderNotification::class)->count(),
        );
    }
}
