<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\LearningClass;
use App\Services\ProgressReportQueryService;
use Inertia\Inertia;
use Inertia\Response;

class ClassProgressReportController extends Controller
{
    public function __construct(private readonly ProgressReportQueryService $reports) {}

    public function show(LearningClass $learningClass): Response
    {
        $this->authorize('viewProgressReport', $learningClass);

        return Inertia::render('reports/ClassProgress', [
            'report' => $this->reports->classReport($learningClass),
            'scope' => 'tutor',
            'backUrl' => route('tutor.classes.show', $learningClass),
            'csvUrl' => null,
        ]);
    }
}
