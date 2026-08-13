<?php

namespace App\Http\Controllers\Student;

use App\Enums\AssessmentAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\User;
use App\Services\AssessmentAttemptService;
use App\Services\StudentAssessmentPayloadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentAttemptController extends Controller
{
    public function __construct(
        private readonly AssessmentAttemptService $attempts,
        private readonly StudentAssessmentPayloadService $payloads,
    ) {}

    public function show(AssessmentAttempt $attempt): Response|RedirectResponse
    {
        $this->authorize('view', $attempt);

        if ($attempt->status !== AssessmentAttemptStatus::InProgress) {
            return to_route('student.assessment-attempts.result', $attempt);
        }

        // The editable attempt contains security-sensitive, mutable state.
        // Encrypt its Inertia history entry so a successful submission can
        // invalidate it for popstate and BFCache restoration.
        Inertia::encryptHistory();

        return Inertia::render('student/assessments/Attempt', [
            'attempt' => $this->payloads->attemptPlayer($attempt),
        ]);
    }

    public function submit(Request $request, AssessmentAttempt $attempt): RedirectResponse
    {
        $this->authorize('submit', $attempt);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->attempts->submit($user, $attempt);
        // Inertia revalidates encrypted entries on both popstate and persisted
        // pageshow. Clearing their key forces a restored attempt page through
        // this controller, where its server-authoritative status redirects it
        // to the Result page instead of restoring stale editable state.
        Inertia::clearHistory();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assessment submitted successfully.')]);

        return to_route('student.assessment-attempts.result', $attempt);
    }

    public function result(AssessmentAttempt $attempt): Response|RedirectResponse
    {
        $this->authorize('view', $attempt);

        if ($attempt->status === AssessmentAttemptStatus::InProgress) {
            return to_route('student.assessment-attempts.show', $attempt);
        }

        return Inertia::render('student/assessments/Result', [
            'result' => $this->payloads->result($attempt),
        ]);
    }
}
