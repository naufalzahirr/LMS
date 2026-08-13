<?php

namespace App\Console\Commands;

use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\LearningClassStatus;
use App\Models\LearningClassAssessment;
use App\Services\AssessmentAvailabilityNotifier;
use Illuminate\Console\Command;

class SendAssessmentAvailabilityNotificationsCommand extends Command
{
    protected $signature = 'notifications:send-assessment-availability';

    protected $description = 'Notify eligible students for assessment assignments that have become available since the last run';

    public function handle(AssessmentAvailabilityNotifier $notifier): int
    {
        // Bounded candidate query: only currently-open assignments, never the
        // full historical set — closed/inactive/unpublished assignments are
        // excluded up front so this stays cheap as the LMS grows.
        $assignments = LearningClassAssessment::query()
            ->where('status', ClassAssessmentStatus::Active->value)
            ->where(fn ($query) => $query->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now()))
            ->whereHas('assessment', fn ($query) => $query->where('status', AssessmentStatus::Published->value))
            ->whereHas('learningClass', fn ($query) => $query->where('status', LearningClassStatus::Active->value))
            ->get();

        $sent = 0;

        foreach ($assignments as $assignment) {
            $sent += $notifier->notify($assignment);
        }

        $this->components->info("Sent {$sent} assessment-availability notification(s) across {$assignments->count()} open assignment(s).");

        return self::SUCCESS;
    }
}
