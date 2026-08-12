<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_checkpoint_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_checkpoint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->json('submitted_answer');
            $table->boolean('is_correct')->index();
            $table->unsignedInteger('attempt_number');
            $table->timestamps();

            $table->unique(
                ['lesson_checkpoint_id', 'enrollment_id', 'attempt_number'],
                'lesson_checkpoint_attempt_number_unique',
            );
            $table->index(
                ['enrollment_id', 'lesson_checkpoint_id', 'is_correct'],
                'lesson_checkpoint_mastery_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_checkpoint_attempts');
    }
};
