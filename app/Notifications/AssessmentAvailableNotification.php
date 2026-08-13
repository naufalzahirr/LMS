<?php

namespace App\Notifications;

use App\Models\LearningClassAssessment;
use Illuminate\Notifications\Notification;

class AssessmentAvailableNotification extends Notification
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
            'title' => 'Assessment available',
            'message' => "{$this->assignment->assessment->title} is now available in {$this->assignment->learningClass->name}.",
            'action_label' => 'Start assessment',
            'action_url' => route('student.assessments.show', [$this->assignment->learningClass, $this->assignment]),
            'entity_type' => 'learning_class_assessment',
            'entity_id' => $this->assignment->id,
        ];
    }
}
