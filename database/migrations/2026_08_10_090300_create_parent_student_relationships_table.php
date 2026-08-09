<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_student_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->string('relationship_type')->index();
            $table->timestamps();

            $table->unique(['parent_id', 'student_id']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_student_relationships');
    }
};
