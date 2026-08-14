<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_checkpoints', function (Blueprint $table): void {
            $table->text('correct_feedback')->nullable()->after('prompt');
            $table->text('incorrect_feedback')->nullable()->after('correct_feedback');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_checkpoints', function (Blueprint $table): void {
            $table->dropColumn(['correct_feedback', 'incorrect_feedback']);
        });
    }
};
