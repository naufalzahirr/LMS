<?php

namespace App\Notifications;

use App\Models\AssessmentAttempt;
use Illuminate\Notifications\Notification;

class ChildAssessmentGradedNotification extends Notification
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
        $this->attempt->loadMissing('enrollment.student');
        $student = $this->attempt->enrollment->student;

        return [
            'title' => 'New assessment result',
            'message' => "A new assessment result is available for {$student->name}.",
            'action_label' => 'View progress',
            'action_url' => route('parent.students.show', $student),
            'entity_type' => 'assessment_attempt',
            'entity_id' => $this->attempt->id,
        ];
    }
}
