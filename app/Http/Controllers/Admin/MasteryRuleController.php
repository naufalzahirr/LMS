<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveMasteryRuleRequest;
use App\Models\Competency;
use App\Models\LearningClass;
use App\Services\MasteryRuleService;
use Illuminate\Http\RedirectResponse;

class MasteryRuleController extends Controller
{
    public function __construct(private readonly MasteryRuleService $service) {}

    public function update(
        SaveMasteryRuleRequest $request,
        LearningClass $learningClass,
        Competency $competency,
    ): RedirectResponse {
        $this->service->save($learningClass, $competency, $request->payload());

        return back();
    }
}
