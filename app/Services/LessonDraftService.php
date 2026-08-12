<?php

namespace App\Services;

use App\Enums\AcademicStatus;
use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\LessonAsset;
use App\Models\Module;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LessonDraftService
{
    public const LIFETIME_HOURS = 24;

    public function __construct(
        private readonly LessonContentService $content,
        private readonly LessonAssetService $assets,
    ) {}

    public function ensure(User $author, Module $module, ?Lesson $draft = null): Lesson
    {
        return DB::transaction(function () use ($author, $module, $draft): Lesson {
            if ($draft instanceof Lesson) {
                $locked = Lesson::query()->lockForUpdate()->findOrFail($draft->id);

                if (! $locked->is_authoring_draft || $locked->draft_owner_id !== $author->id) {
                    throw ValidationException::withMessages([
                        'draft_id' => __('This lesson draft is no longer available.'),
                    ]);
                }

                $locked->forceFill([
                    'module_id' => $module->id,
                    'status' => AcademicStatus::Inactive,
                    'draft_expires_at' => now()->addHours(self::LIFETIME_HOURS),
                ])->save();

                return $locked->refresh();
            }

            return Lesson::query()->create([
                'module_id' => $module->id,
                'title' => 'Untitled lesson draft',
                'slug' => 'draft-'.Str::lower((string) Str::ulid()),
                'lesson_type' => LessonType::Text,
                'content_document' => $this->content->emptyDocument(),
                'sort_order' => 0,
                'status' => AcademicStatus::Inactive,
                'is_authoring_draft' => true,
                'draft_owner_id' => $author->id,
                'draft_expires_at' => now()->addHours(self::LIFETIME_HOURS),
            ]);
        });
    }

    public function extend(Lesson $draft): void
    {
        Lesson::query()
            ->whereKey($draft->id)
            ->where('is_authoring_draft', true)
            ->update(['draft_expires_at' => now()->addHours(self::LIFETIME_HOURS)]);
    }

    public function discard(Lesson $draft): void
    {
        if (! $draft->is_authoring_draft) {
            throw ValidationException::withMessages([
                'draft_id' => __('Only an unfinished lesson draft can be discarded.'),
            ]);
        }

        if (! $this->destroyDraft($draft)) {
            throw ValidationException::withMessages([
                'draft_id' => __('This lesson draft is no longer available.'),
            ]);
        }
    }

    public function pruneExpiredDrafts(): int
    {
        $count = 0;

        Lesson::query()
            ->where('is_authoring_draft', true)
            ->whereNotNull('draft_expires_at')
            ->where('draft_expires_at', '<=', now())
            ->orderBy('id')
            ->eachById(function (Lesson $draft) use (&$count): void {
                $fresh = Lesson::query()
                    ->whereKey($draft->id)
                    ->where('is_authoring_draft', true)
                    ->where('draft_expires_at', '<=', now())
                    ->first();

                if ($fresh instanceof Lesson) {
                    if ($this->destroyDraft($fresh, true)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function pruneUnusedAssets(CarbonInterface $olderThan): int
    {
        $count = 0;

        LessonAsset::query()
            ->where('created_at', '<=', $olderThan)
            ->whereHas('lesson', fn ($query) => $query
                ->where('is_authoring_draft', false)
                ->where('updated_at', '<=', $olderThan)
                ->whereNull('deleted_at'))
            ->with('lesson')
            ->orderBy('id')
            ->eachById(function (LessonAsset $asset) use (&$count): void {
                $asset->lesson->refresh();
                $references = $this->content->referencedAssetIds($asset->lesson->content_document);

                if (! in_array($asset->id, $references, true)) {
                    $this->assets->delete($asset);
                    $count++;
                }
            });

        return $count;
    }

    public function pruneUnusedCheckpoints(CarbonInterface $olderThan): int
    {
        $count = 0;

        Lesson::query()
            ->where('is_authoring_draft', false)
            ->where('updated_at', '<=', $olderThan)
            ->whereNull('deleted_at')
            ->whereHas('checkpoints', fn ($query) => $query->where('created_at', '<=', $olderThan))
            ->orderBy('id')
            ->eachById(function (Lesson $lesson) use (&$count, $olderThan): void {
                $referenced = $this->content->referencedCheckpointIds($lesson->content_document);
                $query = $lesson->checkpoints()->where('created_at', '<=', $olderThan);

                if ($referenced !== []) {
                    $query->whereNotIn('id', $referenced);
                }

                $count += $query->delete();
            });

        return $count;
    }

    public function pruneDeletedLessonAssets(CarbonInterface $olderThan): int
    {
        $count = 0;

        Lesson::onlyTrashed()
            ->where('deleted_at', '<=', $olderThan)
            ->whereHas('assets')
            ->orderBy('id')
            ->eachById(function (Lesson $lesson) use (&$count): void {
                $count += $lesson->assets()->count();
                $lesson->assets()->delete();
                Storage::disk('local')->deleteDirectory("lesson-assets/{$lesson->id}");
            });

        return $count;
    }

    private function destroyDraft(Lesson $draft, bool $onlyIfExpired = false): bool
    {
        $deleted = DB::transaction(function () use ($draft, $onlyIfExpired): bool {
            $locked = Lesson::query()->lockForUpdate()->find($draft->id);

            if (! $locked instanceof Lesson
                || ! $locked->is_authoring_draft
                || ($onlyIfExpired && ($locked->draft_expires_at === null || $locked->draft_expires_at->isFuture()))) {
                return false;
            }

            $locked->assets()->delete();
            $locked->checkpoints()->delete();
            $locked->forceDelete();

            return true;
        });

        if (! $deleted) {
            return false;
        }

        Storage::disk('local')->deleteDirectory("lesson-assets/{$draft->id}");
        Storage::disk('local')->deleteDirectory("lesson-files/{$draft->id}");

        return true;
    }
}
