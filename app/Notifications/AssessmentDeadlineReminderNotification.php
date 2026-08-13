<?php

namespace App\Notifications;

use App\Models\LearningClassAssessment;
use Illuminate\Notifications\Notification;

class AssessmentDeadlineReminderNotification extends Notification
{
    public function __construct(private readonly LearningClassAssessment $assignment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->assignment->loadMissing(['assessment', 'learningClass']);

        return [
            'title' => 'Assessment deadline approaching',
            'message' => "{$this->assignment->assessment->title} closes {$this->assignment->closes_at?->diffForHumans()}.",
            'action_label' => 'Start assessment',
            'action_url' => route('student.assessments.show', [$this->assignment->learningClass, $this->assignment]),
            'entity_type' => 'learning_class_assessment',
            'entity_id' => $this->assignment->id,
        ];
    }
}
