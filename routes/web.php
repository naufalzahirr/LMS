<?php

use App\Http\Controllers\Admin\CompetencyController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('admin/users', UserController::class)
        ->except('show')
        ->names('admin.users');

    Route::resource('admin/programs', ProgramController::class)
        ->except('show')
        ->names('admin.programs');

    Route::resource('admin/courses', CourseController::class)
        ->except('show')
        ->names('admin.courses');

    Route::resource('admin/competencies', CompetencyController::class)
        ->except('show')
        ->names('admin.competencies');

    Route::resource('admin/modules', ModuleController::class)
        ->except('show')
        ->names('admin.modules');

    Route::get('admin/lessons/{lesson}/file', [LessonController::class, 'file'])
        ->name('admin.lessons.file');

    Route::resource('admin/lessons', LessonController::class)
        ->names('admin.lessons');
});

require __DIR__.'/settings.php';
