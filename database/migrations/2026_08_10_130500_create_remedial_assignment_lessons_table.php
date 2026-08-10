<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remedial_assignment_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('remedial_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['remedial_assignment_id', 'lesson_id'], 'remedial_assignment_lesson_unique');
            $table->index(['remedial_assignment_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remedial_assignment_lessons');
    }
};
