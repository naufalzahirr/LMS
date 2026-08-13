<?php

namespace App\Console\Commands;

use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Models\Enrollment;
use App\Models\LearningClassAssessment;
use App\Models\User;
use App\Notifications\AssessmentDeadlineReminderNotification;
use App\Services\StudentAssessmentPayloadService;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class SendAssessmentDeadlineRemindersCommand extends Command
{
    protected $signature = 'notifications:send-deadline-reminders {--hours=24 : Reminder window before an assessment closes}';

    protected $description = 'Send a one-time reminder to students with an assessment closing within the reminder window';

    public function handle(StudentAssessmentPayloadService $payloads): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $windowEnd = now()->addHours($hours);

        $assignments = LearningClassAssessment::query()
            ->where('status', ClassAssessmentStatus::Active->value)
            ->whereNotNull('closes_at')
            ->where('closes_at', '>', now())
            ->where('closes_at', '<=', $windowEnd)
            ->whereHas('assessment', fn ($query) => $query->where('status', AssessmentStatus::Published->value))
            ->whereHas('learningClass', fn ($query) => $query->where('status', LearningClassStatus::Active->value))
            ->get();

        $sent = 0;

        foreach ($assignments as $assignment) {
            $sent += $this->remindEligibleStudents($assignment, $payloads);
        }

        $this->components->info("Sent {$sent} deadline reminder notification(s).");

        return self::SUCCESS;
    }

    private function remindEligibleStudents(LearningClassAssessment $assignment, StudentAssessmentPayloadService $payloads): int
    {
        $enrollments = Enrollment::query()
            ->where('learning_class_id', $assignment->learning_class_id)
            ->where('status', EnrollmentStatus::Active->value)
            ->with('student')
            ->get();
        $sent = 0;

        foreach ($enrollments as $enrollment) {
            $card = collect($payloads->assignmentsForEnrollment($enrollment))->firstWhere('id', $assignment->id);

            if (($card['availability'] ?? null) !== 'Available') {
                continue;
            }

            if ($this->alreadyReminded($enrollment->student, $assignment)) {
                continue;
            }

            $enrollment->student->notify(new AssessmentDeadlineReminderNotification($assignment));
            $sent++;
        }

        return $sent;
    }

    private function alreadyReminded(User $student, LearningClassAssessment $assignment): bool
    {
        return DatabaseNotification::query()
            ->where('notifiable_id', $student->id)
            ->where('notifiable_type', User::class)
            ->where('type', AssessmentDeadlineReminderNotification::class)
            ->whereJsonContains('data->entity_id', $assignment->id)
            ->exists();
    }
}
