<?php

use App\Http\Controllers\Admin\CompetencyController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Admin\LearningClassController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\ParentStudentRelationshipController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\TutorAssignmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Tutor\LearningClassController as TutorLearningClassController;
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

    Route::post('admin/classes/{learningClass}/enrollments', [EnrollmentController::class, 'store'])
        ->name('admin.classes.enrollments.store');
    Route::patch('admin/classes/{learningClass}/enrollments/{enrollment}/withdraw', [EnrollmentController::class, 'withdraw'])
        ->name('admin.classes.enrollments.withdraw');
    Route::patch('admin/classes/{learningClass}/enrollments/{enrollment}/reactivate', [EnrollmentController::class, 'reactivate'])
        ->name('admin.classes.enrollments.reactivate');
    Route::patch('admin/classes/{learningClass}/enrollments/{enrollment}/complete', [EnrollmentController::class, 'complete'])
        ->name('admin.classes.enrollments.complete');
    Route::post('admin/classes/{learningClass}/tutors', [TutorAssignmentController::class, 'store'])
        ->name('admin.classes.tutors.store');
    Route::delete('admin/classes/{learningClass}/tutors/{tutor}', [TutorAssignmentController::class, 'destroy'])
        ->name('admin.classes.tutors.destroy');
    Route::resource('admin/classes', LearningClassController::class)
        ->parameters(['classes' => 'learningClass'])
        ->names('admin.classes');

    Route::resource('admin/parent-students', ParentStudentRelationshipController::class)
        ->only(['index', 'create', 'store', 'destroy'])
        ->parameters(['parent-students' => 'parentStudent'])
        ->names('admin.parent-students');

    Route::get('admin/lessons/{lesson}/file', [LessonController::class, 'file'])
        ->name('admin.lessons.file');

    Route::resource('admin/lessons', LessonController::class)
        ->names('admin.lessons');

    Route::get('tutor/classes', [TutorLearningClassController::class, 'index'])
        ->name('tutor.classes.index');
    Route::get('tutor/classes/{learningClass}', [TutorLearningClassController::class, 'show'])
        ->name('tutor.classes.show');
});

require __DIR__.'/settings.php';
