<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_attempt_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_attempt_question_id')
                ->constrained(indexName: 'attempt_option_question_foreign')
                ->restrictOnDelete();
            $table->text('option_text');
            $table->boolean('is_correct');
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->index(['assessment_attempt_question_id', 'sort_order'], 'attempt_option_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempt_options');
    }
};
