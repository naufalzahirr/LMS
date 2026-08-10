<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompetencyPrerequisiteRequest;
use App\Models\Competency;
use App\Services\CompetencyPrerequisiteService;
use Illuminate\Http\RedirectResponse;

class CompetencyPrerequisiteController extends Controller
{
    public function __construct(private readonly CompetencyPrerequisiteService $service) {}

    public function store(StoreCompetencyPrerequisiteRequest $request, Competency $competency): RedirectResponse
    {
        $this->service->add($competency, $request->prerequisite());

        return back();
    }

    public function destroy(Competency $competency, Competency $prerequisite): RedirectResponse
    {
        $this->authorize('update', $competency);
        $this->service->remove($competency, $prerequisite);

        return back();
    }
}
