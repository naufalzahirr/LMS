<?php

namespace App\Http\Controllers\Admin;

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
     * Serve a question image to whoever may grade this attempt.
     *
     * Shared by the Admin and Tutor grading screens because
     * AssessmentAttemptPolicy::grade already expresses both: an Admin with
     * manage-assessments, or a Tutor assigned to the attempt's own class. A
     * Tutor outside that class fails the same check the grading page uses.
     */
    public function show(
        Request $request,
        AssessmentAttempt $attempt,
        AssessmentAttemptQuestion $attemptQuestion,
    ): StreamedResponse {
        $this->authorize('grade', $attempt);
        abort_unless($attemptQuestion->assessment_attempt_id === $attempt->id, 404);

        $asset = $attemptQuestion->questionAsset;
        abort_unless($asset instanceof QuestionAsset, 404);

        return $this->images->response($request, $asset);
    }
}
