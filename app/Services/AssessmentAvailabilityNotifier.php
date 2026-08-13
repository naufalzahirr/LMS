<?php

namespace App\Services;

use App\Enums\AssessmentStatus;
use App\Enums\ClassAssessmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningClassStatus;
use App\Models\Enrollment;
use App\Models\LearningClassAssessment;
use App\Models\User;
use App\Notifications\AssessmentAvailableNotification;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The single source of truth for "who should receive an assessment-available
 * notification for this assignment right now" — reused by the immediate
 * activation event, manual admin/tutor update paths, and the scheduled
 * reconciliation command, so all three can never disagree or double-notify.
 */
class AssessmentAvailabilityNotifier
{
    public function __construct(private readonly StudentAssessmentPayloadService $payloads) {}

    /** Notify newly-eligible active students for this assignment. Safe to call repeatedly. */
    public function notify(LearningClassAssessment $assignment): int
    {
        if (! $this->isOpen($assignment)) {
            return 0;
        }

        $alreadyNotified = DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('type', AssessmentAvailableNotification::class)
            ->whereJsonContains('data->entity_id', $assignment->id)
            ->pluck('notifiable_id');

        $enrollments = Enrollment::query()
            ->where('learning_class_id', $assignment->learning_class_id)
            ->where('status', EnrollmentStatus::Active->value)
            ->when($alreadyNotified->isNotEmpty(), fn ($query) => $query->whereNotIn('student_id', $alreadyNotified))
            ->with('student')
            ->get();
        $sent = 0;

        foreach ($enrollments as $enrollment) {
            $card = collect($this->payloads->assignmentsForEnrollment($enrollment))->firstWhere('id', $assignment->id);

            if (($card['availability'] ?? null) !== 'Available') {
                continue;
            }

            $enrollment->student->notify(new AssessmentAvailableNotification($assignment));
            $sent++;
        }

        return $sent;
    }

    private function isOpen(LearningClassAssessment $assignment): bool
    {
        $assignment->loadMissing('assessment', 'learningClass');

        return $assignment->status === ClassAssessmentStatus::Active
            && $assignment->assessment->status === AssessmentStatus::Published
            && $assignment->learningClass->status === LearningClassStatus::Active
            && ($assignment->opens_at === null || $assignment->opens_at->isPast())
            && ($assignment->closes_at === null || $assignment->closes_at->isFuture());
    }
}
