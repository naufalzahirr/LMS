<?php

namespace App\Notifications;

use App\Models\RemedialAssignment;
use Illuminate\Notifications\Notification;

class StudentNeedsRemedialNotification extends Notification
{
    public function __construct(private readonly RemedialAssignment $assignment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->assignment->loadMissing('competency', 'enrollment.student', 'enrollment.learningClass');
        $enrollment = $this->assignment->enrollment;

        return [
            'title' => 'Student needs remedial support',
            'message' => "{$enrollment->student->name} needs remedial support for {$this->assignment->competency->name}.",
            'action_label' => 'View student progress',
            'action_url' => route('tutor.classes.show', $enrollment->learningClass),
            'entity_type' => 'remedial_assignment',
            'entity_id' => $this->assignment->id,
        ];
    }
}
