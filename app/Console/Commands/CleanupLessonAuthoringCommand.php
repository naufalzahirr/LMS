<?php

namespace App\Console\Commands;

use App\Services\LessonDraftService;
use Illuminate\Console\Command;

class CleanupLessonAuthoringCommand extends Command
{
    protected $signature = 'lesson-authoring:cleanup {--asset-hours=24 : Grace period before an unused asset is removed}';

    protected $description = 'Remove expired lesson drafts and stale private lesson assets';

    public function handle(LessonDraftService $drafts): int
    {
        $assetHours = max(1, (int) $this->option('asset-hours'));
        $olderThan = now()->subHours($assetHours);
        $expiredDrafts = $drafts->pruneExpiredDrafts();
        $unusedAssets = $drafts->pruneUnusedAssets($olderThan);
        $deletedAssets = $drafts->pruneDeletedLessonAssets($olderThan);

        $this->components->info(
            "Removed {$expiredDrafts} expired draft(s), {$unusedAssets} unused asset(s), and {$deletedAssets} deleted-lesson asset(s).",
        );

        return self::SUCCESS;
    }
}
