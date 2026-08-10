<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_attempt_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_attempt_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->string('question_type');
            $table->text('prompt');
            $table->text('explanation')->nullable();
            $table->decimal('points', 8, 2);
            $table->unsignedInteger('sort_order');
            $table->boolean('correct_boolean')->nullable();
            $table->timestamps();

            $table->index(['assessment_attempt_id', 'sort_order'], 'attempt_question_order_index');
            $table->index('source_question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempt_questions');
    }
};
