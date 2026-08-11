<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->boolean('is_authoring_draft')->default(false)->after('status');
            $table->foreignId('draft_owner_id')
                ->nullable()
                ->after('is_authoring_draft')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('draft_expires_at')->nullable()->after('draft_owner_id');

            $table->index(['is_authoring_draft', 'draft_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropIndex(['is_authoring_draft', 'draft_expires_at']);
            $table->dropConstrainedForeignId('draft_owner_id');
            $table->dropColumn(['is_authoring_draft', 'draft_expires_at']);
        });
    }
};
