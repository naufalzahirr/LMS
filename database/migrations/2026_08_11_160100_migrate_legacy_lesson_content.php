<?php

use App\Services\LessonContentMigrationService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(LessonContentMigrationService::class)->migrateAll();
    }

    public function down(): void
    {
        // Intentionally non-destructive. The preceding schema migration owns rollback.
    }
};
