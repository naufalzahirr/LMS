<?php

namespace App\Notifications;

use App\Models\AssessmentAttempt;
use Illuminate\Notifications\Notification;

class AssessmentNeedsGradingNotification extends Notification
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
        $this->attempt->loadMissing('classAssessment.assessment', 'classAssessment.learningClass');
        $assignment = $this->attempt->classAssessment;

        return [
            'title' => 'Submission needs grading',
            'message' => "A submission for {$assignment->assessment->title} in {$assignment->learningClass->name} is ready for grading.",
            'action_label' => 'Review submission',
            'action_url' => route('tutor.class-assessment-attempts.index', [$assignment->learningClass, $assignment]).'?status=pending_grading',
            'entity_type' => 'assessment_attempt',
            'entity_id' => $this->attempt->id,
        ];
    }
}
