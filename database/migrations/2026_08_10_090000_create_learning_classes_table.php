<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_classes');
    }
};
