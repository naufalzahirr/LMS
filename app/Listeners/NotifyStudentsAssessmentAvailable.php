<?php

namespace App\Listeners;

use App\Enums\EnrollmentStatus;
use App\Events\LearningClassAssessmentActivated;
use App\Models\Enrollment;
use App\Notifications\AssessmentAvailableNotification;
use App\Services\StudentAssessmentPayloadService;

class NotifyStudentsAssessmentAvailable
{
    public function __construct(private readonly StudentAssessmentPayloadService $payloads) {}

    public function handle(LearningClassAssessmentActivated $event): void
    {
        $enrollments = Enrollment::query()
            ->where('learning_class_id', $event->assignment->learning_class_id)
            ->where('status', EnrollmentStatus::Active->value)
            ->with('student')
            ->get();

        foreach ($enrollments as $enrollment) {
            $card = collect($this->payloads->assignmentsForEnrollment($enrollment))
                ->firstWhere('id', $event->assignment->id);

            if (($card['availability'] ?? null) !== 'Available') {
                continue;
            }

            $enrollment->student->notify(new AssessmentAvailableNotification($event->assignment));
        }
    }
}
