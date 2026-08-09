<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('learning_objectives')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['course_id', 'code']);
            $table->unique(['course_id', 'slug']);
            $table->index(['course_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competencies');
    }
};
