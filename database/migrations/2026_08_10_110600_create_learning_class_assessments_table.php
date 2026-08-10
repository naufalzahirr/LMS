<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_class_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learning_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->unsignedInteger('max_attempts')->default(1);
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['learning_class_id', 'assessment_id'], 'class_assessment_unique');
            $table->index(['learning_class_id', 'status']);
            $table->index('assessment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_class_assessments');
    }
};
