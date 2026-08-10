<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remedial_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('competency_id')->constrained()->restrictOnDelete();
            $table->foreignId('mastery_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_assessment_attempt_id')->constrained('assessment_attempts')->restrictOnDelete();
            $table->string('status');
            $table->boolean('open_slot')->nullable();
            $table->dateTime('assigned_at');
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'status']);
            $table->index(['competency_id', 'status']);
            $table->index(['mastery_rule_id', 'status']);
            $table->index(['enrollment_id', 'competency_id', 'status'], 'enrollment_competency_remedial_status_index');
            $table->unique(['enrollment_id', 'competency_id', 'open_slot'], 'one_open_remedial_assignment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remedial_assignments');
    }
};
