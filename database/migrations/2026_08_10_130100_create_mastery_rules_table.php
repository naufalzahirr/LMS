<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mastery_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learning_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('competency_id')->constrained()->restrictOnDelete();
            $table->foreignId('learning_class_assessment_id')->constrained()->restrictOnDelete();
            $table->decimal('mastery_score', 5, 2);
            $table->boolean('require_remedial')->default(true);
            $table->string('status')->index();
            $table->timestamps();

            $table->unique(['learning_class_id', 'competency_id'], 'class_competency_mastery_rule_unique');
            $table->index(['competency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mastery_rules');
    }
};
