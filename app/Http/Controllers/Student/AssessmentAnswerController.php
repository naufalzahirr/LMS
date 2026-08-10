<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SaveAssessmentAnswerRequest;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptQuestion;
use App\Models\User;
use App\Services\AssessmentAnswerService;
use Illuminate\Http\RedirectResponse;

class AssessmentAnswerController extends Controller
{
    public function __construct(private readonly AssessmentAnswerService $answers) {}

    public function update(
        SaveAssessmentAnswerRequest $request,
        AssessmentAttempt $attempt,
        AssessmentAttemptQuestion $attemptQuestion,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->answers->save($user, $attempt, $attemptQuestion, $request->payload());

        return back();
    }
}
