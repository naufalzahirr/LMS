<?php

use App\Models\User;
use App\Notifications\AssessmentGradedNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $studentByAttempt = DB::table('assessment_attempts')
            ->join('enrollments', 'enrollments.id', '=', 'assessment_attempts.enrollment_id')
            ->pluck('enrollments.student_id', 'assessment_attempts.id');

        DB::table('notifications')
            ->where('type', AssessmentGradedNotification::class)
            ->where('notifiable_type', User::class)
            ->orderBy('id')
            ->chunkById(100, function ($notifications) use ($studentByAttempt): void {
                $invalidIds = $notifications->filter(function (object $notification) use ($studentByAttempt): bool {
                    $data = json_decode((string) $notification->data, true);
                    $attemptId = is_array($data) ? ($data['entity_id'] ?? null) : null;
                    $intendedStudentId = is_numeric($attemptId)
                        ? $studentByAttempt->get((int) $attemptId)
                        : null;

                    return $intendedStudentId === null
                        || (int) $notification->notifiable_id !== (int) $intendedStudentId;
                })->pluck('id');

                if ($invalidIds->isNotEmpty()) {
                    DB::table('notifications')->whereIn('id', $invalidIds)->delete();
                }
            }, 'id');
    }

    public function down(): void
    {
        // Invalid cross-user notifications cannot be safely reconstructed.
    }
};
