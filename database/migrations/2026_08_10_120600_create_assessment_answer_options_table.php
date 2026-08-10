<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_answer_options', function (Blueprint $table): void {
            $table->foreignId('assessment_answer_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_attempt_option_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['assessment_answer_id', 'assessment_attempt_option_id'], 'answer_attempt_option_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_answer_options');
    }
};
