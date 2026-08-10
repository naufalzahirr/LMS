<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_attempt_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_attempt_question_id')->constrained()->restrictOnDelete();
            $table->longText('answer_text')->nullable();
            $table->boolean('answer_boolean')->nullable();
            $table->decimal('auto_score', 8, 2)->nullable();
            $table->decimal('manual_score', 8, 2)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_attempt_id', 'assessment_attempt_question_id'], 'attempt_question_answer_unique');
            $table->index(['assessment_attempt_id', 'graded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_answers');
    }
};
