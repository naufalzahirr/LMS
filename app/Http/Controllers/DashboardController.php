<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User
            && $user->hasRole('Parent')
            && ! $user->hasAnyRole(['Admin', 'Tutor', 'Student'])) {
            return to_route('parent.dashboard');
        }

        return Inertia::render('Dashboard');
    }
}
