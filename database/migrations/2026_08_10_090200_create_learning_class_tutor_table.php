<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_class_tutor', function (Blueprint $table): void {
            $table->foreignId('learning_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('tutor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['learning_class_id', 'tutor_id']);
            $table->index('tutor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_class_tutor');
    }
};
