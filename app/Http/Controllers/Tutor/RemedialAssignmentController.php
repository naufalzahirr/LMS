<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Admin\RemedialAssignmentController as BaseRemedialAssignmentController;

class RemedialAssignmentController extends BaseRemedialAssignmentController
{
    protected function routePrefix(): string
    {
        return 'tutor';
    }
}
