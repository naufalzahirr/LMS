<?php

namespace App\Notifications;

use App\Models\RemedialAssignment;
use Illuminate\Notifications\Notification;

class ChildRemedialAssignedNotification extends Notification
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
        $this->assignment->loadMissing('competency', 'enrollment.student');
        $student = $this->assignment->enrollment->student;

        return [
            'title' => 'Remedial learning assigned',
            'message' => "{$student->name} was assigned remedial learning for {$this->assignment->competency->name}.",
            'action_label' => 'View progress',
            'action_url' => route('parent.students.show', $student),
            'entity_type' => 'remedial_assignment',
            'entity_id' => $this->assignment->id,
        ];
    }
}
