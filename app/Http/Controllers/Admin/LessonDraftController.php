<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\LessonDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LessonDraftController extends Controller
{
    public function __construct(private readonly LessonDraftService $drafts) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => ['required', 'integer', 'exists:modules,id'],
            'draft_id' => ['nullable', 'integer', 'exists:lessons,id'],
        ]);
        $module = Module::query()->findOrFail((int) $validated['module_id']);
        $this->authorize('create', [Lesson::class, $module]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $draft = null;

        if (isset($validated['draft_id'])) {
            $draft = Lesson::query()->findOrFail((int) $validated['draft_id']);
            abort_unless($draft->is_authoring_draft, 404);
            $this->authorize('update', $draft);
        }

        return response()->json([
            'draft' => $this->payload($this->drafts->ensure($user, $module, $draft)),
        ]);
    }

    public function destroy(Lesson $lesson): Response
    {
        abort_unless($lesson->is_authoring_draft, 404);
        $this->authorize('delete', $lesson);
        $this->drafts->discard($lesson);

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function payload(Lesson $draft): array
    {
        return [
            'id' => $draft->id,
            'expires_at' => $draft->draft_expires_at?->toIso8601String(),
            'asset_upload_url' => route('admin.lesson-assets.store', $draft),
            'checkpoint_url' => route('admin.lesson-checkpoints.store', $draft),
            'preview_url' => route('admin.lessons.preview', $draft),
            'discard_url' => route('admin.lesson-drafts.destroy', $draft),
        ];
    }
}
