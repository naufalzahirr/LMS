<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StudentProgressInsightsQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgressInsightController extends Controller
{
    public function __construct(private readonly StudentProgressInsightsQueryService $insights) {}

    public function __invoke(Request $request): Response
    {
        $student = $request->user();
        abort_unless($student instanceof User, 401);
        abort_unless($student->hasRole('Student') && $student->hasPermissionTo('view-own-progress'), 403);

        return Inertia::render('student/progress/Index', [
            'insights' => $this->insights->forStudent($student),
        ]);
    }
}
