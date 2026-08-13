<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SaveAssessmentAnswerRequest;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptQuestion;
use App\Models\User;
use App\Services\AssessmentAnswerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class AssessmentAnswerController extends Controller
{
    public function __construct(private readonly AssessmentAnswerService $answers) {}

    public function update(
        SaveAssessmentAnswerRequest $request,
        AssessmentAttempt $attempt,
        AssessmentAttemptQuestion $attemptQuestion,
    ): RedirectResponse|Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->answers->save($user, $attempt, $attemptQuestion, $request->payload());

        // Student answer autosave is a standalone JSON request (Inertia's
        // useHttp), never a page visit — it must not receive a redirect/page
        // response. Any other caller keeps the existing redirect-back behavior.
        if ($request->wantsJson()) {
            return response()->noContent();
        }

        return back();
    }
}
