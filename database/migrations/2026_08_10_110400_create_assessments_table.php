<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competency_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('purpose')->index();
            $table->string('status')->default('draft')->index();
            $table->text('instructions')->nullable();
            $table->boolean('shuffle_questions')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['competency_id', 'code']);
            $table->index(['competency_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
