<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->json('content_document')->nullable()->after('file_path');
        });

        Schema::create('lesson_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('asset_type')->index();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->timestamps();

            $table->unique(['lesson_id', 'file_path']);
            $table->index(['lesson_id', 'asset_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_assets');

        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn('content_document');
        });
    }
};
