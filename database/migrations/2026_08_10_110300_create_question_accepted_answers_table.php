<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_accepted_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->text('answer_text');
            $table->boolean('case_sensitive')->default(false);
            $table->timestamps();

            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_accepted_answers');
    }
};
