<?php

use App\Http\Controllers\Admin\AssessmentController;
use App\Http\Controllers\Admin\AssessmentQuestionController;
use App\Http\Controllers\Admin\ClassAssessmentController;
use App\Http\Controllers\Admin\CompetencyController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Admin\LearningClassController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\ParentStudentRelationshipController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\QuestionBankController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\TutorAssignmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Student\LearningClassController as StudentLearningClassController;
use App\Http\Controllers\Student\LessonController as StudentLessonController;
use App\Http\Controllers\Student\LessonProgressController as StudentLessonProgressController;
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

    Route::resource('admin/question-banks', QuestionBankController::class)
        ->except('show')
        ->parameters(['question-banks' => 'questionBank'])
        ->names('admin.question-banks');

    Route::resource('admin/questions', QuestionController::class)
        ->names('admin.questions');

    Route::patch('admin/assessments/{assessment}/publish', [AssessmentController::class, 'publish'])
        ->name('admin.assessments.publish');
    Route::patch('admin/assessments/{assessment}/archive', [AssessmentController::class, 'archive'])
        ->name('admin.assessments.archive');
    Route::post('admin/assessments/{assessment}/questions', [AssessmentQuestionController::class, 'store'])
        ->name('admin.assessments.questions.store');
    Route::patch('admin/assessments/{assessment}/questions/{assessmentQuestion}', [AssessmentQuestionController::class, 'update'])
        ->name('admin.assessments.questions.update');
    Route::patch('admin/assessments/{assessment}/questions/{assessmentQuestion}/move/{direction}', [AssessmentQuestionController::class, 'move'])
        ->name('admin.assessments.questions.move');
    Route::delete('admin/assessments/{assessment}/questions/{assessmentQuestion}', [AssessmentQuestionController::class, 'destroy'])
        ->name('admin.assessments.questions.destroy');
    Route::resource('admin/assessments', AssessmentController::class)
        ->names('admin.assessments');

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
    Route::post('admin/classes/{learningClass}/assessments', [ClassAssessmentController::class, 'store'])
        ->name('admin.classes.assessments.store');
    Route::patch('admin/classes/{learningClass}/assessments/{assignment}', [ClassAssessmentController::class, 'update'])
        ->name('admin.classes.assessments.update');
    Route::delete('admin/classes/{learningClass}/assessments/{assignment}', [ClassAssessmentController::class, 'destroy'])
        ->name('admin.classes.assessments.destroy');
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

    Route::get('student/classes', [StudentLearningClassController::class, 'index'])
        ->name('student.classes.index');
    Route::get('student/classes/{learningClass}', [StudentLearningClassController::class, 'show'])
        ->name('student.classes.show');
    Route::get('student/classes/{learningClass}/lessons/{lesson}', [StudentLessonController::class, 'show'])
        ->name('student.lessons.show');
    Route::get('student/classes/{learningClass}/lessons/{lesson}/file', [StudentLessonController::class, 'file'])
        ->name('student.lessons.file');
    Route::patch('student/classes/{learningClass}/lessons/{lesson}/progress', [StudentLessonProgressController::class, 'update'])
        ->name('student.lesson-progress.update');
});

require __DIR__.'/settings.php';
