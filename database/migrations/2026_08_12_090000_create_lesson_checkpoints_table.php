<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->restrictOnDelete();
            $table->string('checkpoint_type')->index();
            $table->text('prompt');
            $table->text('explanation')->nullable();
            $table->json('configuration');
            $table->json('answer_key');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lesson_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_checkpoints');
    }
};
