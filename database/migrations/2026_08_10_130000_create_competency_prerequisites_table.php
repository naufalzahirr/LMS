<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_prerequisites', function (Blueprint $table): void {
            $table->foreignId('competency_id')->constrained()->restrictOnDelete();
            $table->foreignId('prerequisite_competency_id')->constrained('competencies')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['competency_id', 'prerequisite_competency_id'], 'competency_prerequisite_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_prerequisites');
    }
};
