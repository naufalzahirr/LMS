<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learning_class_assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status')->index();
            $table->dateTime('started_at');
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('graded_at')->nullable();
            $table->decimal('auto_points', 10, 2)->nullable();
            $table->decimal('manual_points', 10, 2)->nullable();
            $table->decimal('earned_points', 10, 2)->nullable();
            $table->decimal('max_points', 10, 2);
            $table->decimal('percentage', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['learning_class_assessment_id', 'enrollment_id', 'attempt_number'], 'assessment_attempt_number_unique');
            $table->index(['enrollment_id', 'status']);
            $table->index(['learning_class_assessment_id', 'status'], 'class_assessment_attempt_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempts');
    }
};
