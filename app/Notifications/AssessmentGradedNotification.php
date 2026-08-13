<?php

namespace App\Notifications;

use App\Models\AssessmentAttempt;
use Illuminate\Notifications\Notification;

class AssessmentGradedNotification extends Notification
{
    public function __construct(private readonly AssessmentAttempt $attempt) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
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
