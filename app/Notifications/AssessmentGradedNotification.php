<?php

namespace App\Notifications;

use App\Models\AssessmentAttempt;
use App\Models\User;
use Illuminate\Notifications\Notification;

class AssessmentGradedNotification extends Notification
{
    public function __construct(private readonly AssessmentAttempt $attempt) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $this->attempt->loadMissing('enrollment');

        // This notification contains Student copy and a Student-only route.
        // Refuse delivery even if a caller accidentally includes another role
        // or a different Student in the recipient collection.
        if (! $notifiable instanceof User
            || ! $notifiable->hasRole('Student')
            || $notifiable->id !== $this->attempt->enrollment->student_id) {
            return [];
        }

        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->attempt->loadMissing('classAssessment.assessment');

        return [
            'title' => 'Assessment graded',
            'message' => "Your submission for {$this->attempt->classAssessment->assessment->title} has been graded.",
            'action_label' => 'View result',
            'action_url' => route('student.assessment-attempts.result', $this->attempt),
            'entity_type' => 'assessment_attempt',
            'entity_id' => $this->attempt->id,
        ];
    }
}
