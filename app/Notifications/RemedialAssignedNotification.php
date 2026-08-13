<?php

namespace App\Notifications;

use App\Models\RemedialAssignment;
use Illuminate\Notifications\Notification;

class RemedialAssignedNotification extends Notification
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
        $this->assignment->loadMissing('competency');

        return [
            'title' => 'Remedial learning assigned',
            'message' => "Remedial learning is available for {$this->assignment->competency->name}.",
            'action_label' => 'Continue remedial',
            'action_url' => route('student.remedials.show', $this->assignment),
            'entity_type' => 'remedial_assignment',
            'entity_id' => $this->assignment->id,
        ];
    }
}
