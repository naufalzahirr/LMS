<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionAsset;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class QuestionAssetService
{
    /**
     * Replace (or create) the single optional image of a Question.
     *
     * Structural edits to a Question already used in a Student attempt are
     * rejected by QuestionService; its image follows the same rule so an
     * attempt snapshot can never point at a file the Student never saw.
     */
    public function replace(Question $question, UploadedFile $file, string $altText): QuestionAsset
    {
        $this->ensureNotAttempted($question);

        $previous = $question->image;
        $previousPath = $previous?->managedFilePath();
        $path = $file->store("question-assets/{$question->id}", 'local');

        if (! is_string($path)) {
            throw new RuntimeException('The question image could not be stored.');
        }

        try {
            $asset = QuestionAsset::query()->updateOrCreate(
                ['question_id' => $question->id],
                [
                    'original_name' => $this->safeOriginalName($file),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                    'file_size' => max(0, (int) $file->getSize()),
                    'alt_text' => trim($altText),
                ],
            );
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        // Only after the row points at the new file, so a failed write never
        // leaves the question referencing a file that is already gone.
        if ($previousPath !== null && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        $question->setRelation('image', $asset);

        return $asset;
    }

    public function updateAltText(Question $question, string $altText): ?QuestionAsset
    {
        $asset = $question->image;

        if (! $asset instanceof QuestionAsset) {
            return null;
        }

        $this->ensureNotAttempted($question);
        $asset->update(['alt_text' => trim($altText)]);

        return $asset->refresh();
    }

    public function delete(Question $question): void
    {
        $asset = $question->image;

        if (! $asset instanceof QuestionAsset) {
            return;
        }

        $this->ensureNotAttempted($question);
        $path = $asset->managedFilePath();
        $asset->delete();
        $question->setRelation('image', null);

        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Private streamed response using the same hardening as lesson assets:
     * never cached, never sniffed, and rendered under a null sandbox policy.
     */
    public function response(Request $request, QuestionAsset $asset): StreamedResponse
    {
        $path = $asset->managedFilePath();
        abort_if($path === null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $asset->original_name, [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function ensureNotAttempted(Question $question): void
    {
        if ($question->attemptQuestions()->exists()) {
            throw ValidationException::withMessages([
                'image' => __('This question has already been used in a student attempt and its image cannot be changed.'),
            ]);
        }
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = trim(str_replace(["\0", "\r", "\n"], '', basename($file->getClientOriginalName())));

        return mb_substr($name !== '' ? $name : 'question-image', 0, 255);
    }
}
