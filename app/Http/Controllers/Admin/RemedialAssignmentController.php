<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageRemedialAssignmentRequest;
use App\Models\Lesson;
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
        $this->authorize('manage', $remedialAssignment);

        return Inertia::render('remedials/Manage', $this->payloads->management($remedialAssignment, $this->routePrefix()));
    }

    public function update(ManageRemedialAssignmentRequest $request, RemedialAssignment $remedialAssignment): RedirectResponse
    {
        $user = $this->user($request);
        $this->service->updateNotes($user, $remedialAssignment, $request->filled('notes') ? $request->string('notes')->toString() : null);

        return back();
    }

    public function addLesson(ManageRemedialAssignmentRequest $request, RemedialAssignment $remedialAssignment): RedirectResponse
    {
        $this->service->addLesson($this->user($request), $remedialAssignment, Lesson::query()->findOrFail($request->integer('lesson_id')));

        return back();
    }

    public function removeLesson(Request $request, RemedialAssignment $remedialAssignment, RemedialAssignmentLesson $item): RedirectResponse
    {
        $this->service->removeLesson($this->user($request), $remedialAssignment, $item);

        return back();
    }

    public function complete(Request $request, RemedialAssignment $remedialAssignment): RedirectResponse
    {
        $this->service->completeIntervention($this->user($request), $remedialAssignment);

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    protected function routePrefix(): string
    {
        return 'admin';
    }
}
