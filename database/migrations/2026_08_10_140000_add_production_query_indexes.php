<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->index(
                ['learning_class_id', 'status', 'student_id'],
                'enrollment_class_status_student_index',
            );
        });

        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->index(
                ['enrollment_id', 'submitted_at', 'attempt_number'],
                'attempt_enrollment_submitted_number_index',
            );
            $table->index(
                ['learning_class_assessment_id', 'status', 'submitted_at'],
                'attempt_assignment_status_submitted_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->dropIndex('attempt_enrollment_submitted_number_index');
            $table->dropIndex('attempt_assignment_status_submitted_index');
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropIndex('enrollment_class_status_student_index');
        });
    }
};
