<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_assets', function (Blueprint $table): void {
            $table->id();
            // One optional image per Question. The unique constraint is the
            // schema-level guarantee behind Question::image() being a HasOne.
            $table->foreignId('question_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('alt_text');
            $table->timestamps();
        });

        Schema::table('assessment_attempt_questions', function (Blueprint $table): void {
            // Snapshot column, consistent with the prompt/options/answer-key
            // snapshot this table already keeps: an attempt keeps rendering the
            // image the Student actually saw, independent of later authoring.
            $table->foreignId('question_asset_id')
                ->nullable()
                ->after('source_question_id')
                ->constrained('question_assets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_attempt_questions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('question_asset_id');
        });

        Schema::dropIfExists('question_assets');
    }
};
