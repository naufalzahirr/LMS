<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminDashboardQueryService;
use App\Services\StudentDashboardQueryService;
use App\Services\TutorDashboardQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardQueryService $admin,
        private readonly TutorDashboardQueryService $tutor,
        private readonly StudentDashboardQueryService $student,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if ($user->hasRole('Admin')) {
            return Inertia::render('admin/Dashboard', ['dashboard' => $this->admin->forAdmin()]);
        }

        if ($user->hasRole('Tutor')) {
            return Inertia::render('tutor/Dashboard', ['dashboard' => $this->tutor->forTutor($user)]);
        }

        if ($user->hasRole('Student')) {
            return Inertia::render('student/Dashboard', ['dashboard' => $this->student->forStudent($user)]);
        }

        if ($user->hasRole('Parent')) {
            return to_route('parent.dashboard');
        }

        abort(403);
    }
}
