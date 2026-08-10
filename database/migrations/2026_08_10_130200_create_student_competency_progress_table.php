<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_competency_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('competency_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->decimal('latest_score', 5, 2)->nullable();
            $table->decimal('best_score', 5, 2)->nullable();
            $table->unsignedInteger('total_mastery_attempts')->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('mastered_at')->nullable();
            $table->dateTime('last_evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'competency_id'], 'enrollment_competency_progress_unique');
            $table->index(['enrollment_id', 'status']);
            $table->index(['competency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_competency_progress');
    }
};
