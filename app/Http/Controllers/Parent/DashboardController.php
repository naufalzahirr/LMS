<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ParentProgressQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly ParentProgressQueryService $progress) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->authorize('viewChildDashboard', User::class);

        return Inertia::render('parent/Dashboard', [
            'children' => $this->progress->dashboard($user),
        ]);
    }
}
