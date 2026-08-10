<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_attempt_accepted_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_attempt_question_id')
                ->constrained(indexName: 'attempt_accepted_answer_question_foreign')
                ->restrictOnDelete();
            $table->text('answer_text');
            $table->boolean('case_sensitive');
            $table->timestamps();

            $table->index('assessment_attempt_question_id', 'attempt_accepted_answer_question_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempt_accepted_answers');
    }
};
