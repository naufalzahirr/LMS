<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptQuestion;
use App\Models\QuestionAsset;
use App\Services\QuestionAssetService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentAttemptImageController extends Controller
{
    public function __construct(private readonly QuestionAssetService $images) {}

    /**
     * Serve a question image through the attempt that snapshotted it.
     *
     * Students never address a Question directly, so the only authorization
     * this needs is the one that already governs the attempt itself: the
     * AssessmentAttemptPolicy binds the attempt to the requesting Student's
     * own Enrollment. A Student from another class, another enrollment, or a
     * logged-out visitor is rejected before any file is touched.
     */
    public function show(
        Request $request,
        AssessmentAttempt $attempt,
        AssessmentAttemptQuestion $attemptQuestion,
    ): StreamedResponse {
        $this->authorize('view', $attempt);
        abort_unless($attemptQuestion->assessment_attempt_id === $attempt->id, 404);

        $asset = $attemptQuestion->questionAsset;
        abort_unless($asset instanceof QuestionAsset, 404);

        return $this->images->response($request, $asset);
    }
}
