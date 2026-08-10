<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\RemedialAssignment;
use App\Models\RemedialAssignmentLesson;
use App\Models\User;
use App\Services\RemedialAssignmentPayloadService;
use App\Services\RemedialAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RemedialAssignmentController extends Controller
{
    public function __construct(
        private readonly RemedialAssignmentService $service,
        private readonly RemedialAssignmentPayloadService $payloads,
    ) {}

    public function show(RemedialAssignment $remedialAssignment): Response
    {
        $this->authorize('view', $remedialAssignment);

        return Inertia::render('student/remedials/Show', [
            'remedial' => $this->payloads->student($remedialAssignment),
        ]);
    }

    public function completeLesson(
        Request $request,
        RemedialAssignment $remedialAssignment,
        RemedialAssignmentLesson $item,
    ): RedirectResponse {
        $this->authorize('completeLesson', $remedialAssignment);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->service->completeLesson($user, $remedialAssignment, $item);

        return back();
    }
}
