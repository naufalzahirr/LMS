<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mastery_rule_remedial_lessons', function (Blueprint $table): void {
            $table->foreignId('mastery_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique(['mastery_rule_id', 'lesson_id'], 'mastery_rule_remedial_lesson_unique');
            $table->index(['mastery_rule_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mastery_rule_remedial_lessons');
    }
};
