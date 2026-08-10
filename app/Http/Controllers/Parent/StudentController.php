<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ParentProgressQueryService;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(private readonly ParentProgressQueryService $progress) {}

    public function show(User $student): Response
    {
        $this->authorize('viewChildProgress', $student);

        return Inertia::render('parent/students/Show', [
            'student' => $this->progress->child($student),
            'dashboardUrl' => route('parent.dashboard'),
        ]);
    }
}
