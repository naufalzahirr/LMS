<?php

use App\Http\Controllers\Admin\AssessmentAttemptController as AdminAssessmentAttemptController;
use App\Http\Controllers\Admin\AssessmentAttemptImageController as AdminAssessmentAttemptImageController;
use App\Http\Controllers\Admin\AssessmentController;
use App\Http\Controllers\Admin\AssessmentQuestionController;
use App\Http\Controllers\Admin\ClassAssessmentController;
use App\Http\Controllers\Admin\CompetencyController;
use App\Http\Controllers\Admin\CompetencyPrerequisiteController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Admin\LearningAnalyticsController as AdminLearningAnalyticsController;
use App\Http\Controllers\Admin\LearningClassController;
use App\Http\Controllers\Admin\LessonAssetController;
use App\Http\Controllers\Admin\LessonCheckpointController as AdminLessonCheckpointController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\LessonDraftController;
use App\Http\Controllers\Admin\LessonPreviewController;
use App\Http\Controllers\Admin\MasteryRuleController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\ParentStudentRelationshipController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\ProgressReportController;
use App\Http\Controllers\Admin\QuestionBankController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\RemedialAssignmentController as AdminRemedialAssignmentController;
use App\Http\Controllers\Admin\TutorAssignmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Parent\DashboardController as ParentDashboardController;
use App\Http\Controllers\Parent\StudentController as ParentStudentController;
use App\Http\Controllers\Student\AssessmentAnswerController as StudentAssessmentAnswerController;
use App\Http\Controllers\Student\AssessmentAttemptController as StudentAssessmentAttemptController;
use App\Http\Controllers\Student\AssessmentAttemptImageController as StudentAssessmentAttemptImageController;
use App\Http\Controllers\Student\AssessmentController as StudentAssessmentController;
use App\Http\Controllers\Student\LearningClassController as StudentLearningClassController;
use App\Http\Controllers\Student\LessonAssetController as StudentLessonAssetController;
use App\Http\Controllers\Student\LessonCheckpointController as StudentLessonCheckpointController;
use App\Http\Controllers\Student\LessonController as StudentLessonController;
use App\Http\Controllers\Student\LessonProgressController as StudentLessonProgressController;
use App\Http\Controllers\Student\ProgressInsightController as StudentProgressInsightController;
use App\Http\Controllers\Student\RemedialAssignmentController as StudentRemedialAssignmentController;
use App\Http\Controllers\Tutor\AssessmentAttemptController as TutorAssessmentAttemptController;
use App\Http\Controllers\Tutor\ClassProgressReportController as TutorClassProgressReportController;
use App\Http\Controllers\Tutor\LearningAnalyticsController as TutorLearningAnalyticsController;
use App\Http\Controllers\Tutor\LearningClassController as TutorLearningClassController;
use App\Http\Controllers\Tutor\RemedialAssignmentController as TutorRemedialAssignmentController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('parent/dashboard', ParentDashboardController::class)->name('parent.dashboard');
    Route::get('parent/students/{student}', [ParentStudentController::class, 'show'])->name('parent.students.show');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('admin/reports/progress', [ProgressReportController::class, 'index'])
        ->name('admin.reports.progress.index');
    Route::get('admin/reports/students', [ProgressReportController::class, 'students'])
        ->middleware('throttle:60,1')
        ->name('admin.reports.students');
    Route::get('admin/reports/classes/{learningClass}/progress.csv', [ProgressReportController::class, 'csv'])
        ->name('admin.reports.classes.progress.csv');
    Route::get('admin/reports/classes/{learningClass}', [ProgressReportController::class, 'show'])
        ->name('admin.reports.classes.show');
    Route::get('admin/analytics', [AdminLearningAnalyticsController::class, 'index'])
        ->name('admin.analytics.index');
    Route::get('admin/analytics.csv', [AdminLearningAnalyticsController::class, 'csv'])
        ->name('admin.analytics.csv');

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
    Route::post('admin/competencies/{competency}/prerequisites', [CompetencyPrerequisiteController::class, 'store'])
        ->name('admin.competencies.prerequisites.store');
    Route::delete('admin/competencies/{competency}/prerequisites/{prerequisite}', [CompetencyPrerequisiteController::class, 'destroy'])
        ->name('admin.competencies.prerequisites.destroy');

    Route::resource('admin/modules', ModuleController::class)
        ->except('show')
        ->names('admin.modules');

    Route::resource('admin/question-banks', QuestionBankController::class)
        ->except('show')
        ->parameters(['question-banks' => 'questionBank'])
        ->names('admin.question-banks');

    Route::get('admin/questions/{question}/image', [QuestionController::class, 'image'])
        ->name('admin.questions.image');
    Route::resource('admin/questions', QuestionController::class)
        ->names('admin.questions');
    // Shared by the Admin and Tutor grading screens; the attempt policy's
    // grade ability already covers both roles.
    Route::get('admin/assessment-attempts/{attempt}/questions/{attemptQuestion}/image', [AdminAssessmentAttemptImageController::class, 'show'])
        ->name('admin.attempt-question-images.show');

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
    Route::put('admin/classes/{learningClass}/competencies/{competency}/mastery-rule', [MasteryRuleController::class, 'update'])
        ->name('admin.classes.mastery-rules.update');
    Route::get('admin/classes/{learningClass}/assessments/{assignment}/attempts', [AdminAssessmentAttemptController::class, 'index'])
        ->name('admin.class-assessment-attempts.index');
    Route::get('admin/classes/{learningClass}/assessments/{assignment}/attempts/{attempt}/grade', [AdminAssessmentAttemptController::class, 'edit'])
        ->name('admin.class-assessment-attempts.edit');
    Route::patch('admin/classes/{learningClass}/assessments/{assignment}/attempts/{attempt}', [AdminAssessmentAttemptController::class, 'update'])
        ->name('admin.class-assessment-attempts.update');
    Route::resource('admin/classes', LearningClassController::class)
        ->parameters(['classes' => 'learningClass'])
        ->names('admin.classes');
    Route::get('admin/remedials/{remedialAssignment}', [AdminRemedialAssignmentController::class, 'show'])->name('admin.remedials.show');
    Route::patch('admin/remedials/{remedialAssignment}', [AdminRemedialAssignmentController::class, 'update'])->name('admin.remedials.update');
    Route::patch('admin/remedials/{remedialAssignment}/complete', [AdminRemedialAssignmentController::class, 'complete'])->name('admin.remedials.complete');
    Route::post('admin/remedials/{remedialAssignment}/lessons', [AdminRemedialAssignmentController::class, 'addLesson'])->name('admin.remedial-lessons.store');
    Route::delete('admin/remedials/{remedialAssignment}/lessons/{item}', [AdminRemedialAssignmentController::class, 'removeLesson'])->name('admin.remedial-lessons.destroy');

    Route::resource('admin/parent-students', ParentStudentRelationshipController::class)
        ->only(['index', 'create', 'store', 'destroy'])
        ->parameters(['parent-students' => 'parentStudent'])
        ->names('admin.parent-students');

    Route::get('admin/lessons/{lesson}/file', [LessonController::class, 'file'])
        ->name('admin.lessons.file');
    Route::post('admin/lesson-drafts', [LessonDraftController::class, 'store'])
        ->name('admin.lesson-drafts.store');
    Route::delete('admin/lesson-drafts/{lesson}', [LessonDraftController::class, 'destroy'])
        ->name('admin.lesson-drafts.destroy');
    Route::post('admin/lessons/{lesson}/preview', [LessonPreviewController::class, 'store'])
        ->name('admin.lessons.preview');
    Route::post('admin/lessons/{lesson}/assets', [LessonAssetController::class, 'store'])
        ->name('admin.lesson-assets.store');
    Route::post('admin/lessons/{lesson}/checkpoints', [AdminLessonCheckpointController::class, 'store'])
        ->name('admin.lesson-checkpoints.store');
    Route::patch('admin/lessons/{lesson}/checkpoints/{checkpoint}', [AdminLessonCheckpointController::class, 'update'])
        ->name('admin.lesson-checkpoints.update');
    Route::patch('admin/lessons/{lesson}/assets/{asset}', [LessonAssetController::class, 'update'])
        ->name('admin.lesson-assets.update');
    Route::delete('admin/lessons/{lesson}/assets/{asset}', [LessonAssetController::class, 'destroy'])
        ->name('admin.lesson-assets.destroy');
    Route::get('admin/lessons/{lesson}/assets/{asset}/file', [LessonAssetController::class, 'file'])
        ->name('admin.lesson-assets.file');

    Route::resource('admin/lessons', LessonController::class)
        ->names('admin.lessons');

    Route::get('tutor/classes', [TutorLearningClassController::class, 'index'])
        ->name('tutor.classes.index');
    Route::get('tutor/analytics', [TutorLearningAnalyticsController::class, 'index'])
        ->name('tutor.analytics.index');
    Route::get('tutor/analytics.csv', [TutorLearningAnalyticsController::class, 'csv'])
        ->name('tutor.analytics.csv');
    Route::get('tutor/classes/{learningClass}', [TutorLearningClassController::class, 'show'])
        ->name('tutor.classes.show');
    Route::get('tutor/reports/classes/{learningClass}', [TutorClassProgressReportController::class, 'show'])
        ->name('tutor.reports.classes.show');
    Route::get('tutor/classes/{learningClass}/assessments/{assignment}/attempts', [TutorAssessmentAttemptController::class, 'index'])
        ->name('tutor.class-assessment-attempts.index');
    Route::get('tutor/classes/{learningClass}/assessments/{assignment}/attempts/{attempt}/grade', [TutorAssessmentAttemptController::class, 'edit'])
        ->name('tutor.class-assessment-attempts.edit');
    Route::patch('tutor/classes/{learningClass}/assessments/{assignment}/attempts/{attempt}', [TutorAssessmentAttemptController::class, 'update'])
        ->name('tutor.class-assessment-attempts.update');
    Route::get('tutor/remedials/{remedialAssignment}', [TutorRemedialAssignmentController::class, 'show'])->name('tutor.remedials.show');
    Route::patch('tutor/remedials/{remedialAssignment}', [TutorRemedialAssignmentController::class, 'update'])->name('tutor.remedials.update');
    Route::patch('tutor/remedials/{remedialAssignment}/complete', [TutorRemedialAssignmentController::class, 'complete'])->name('tutor.remedials.complete');
    Route::post('tutor/remedials/{remedialAssignment}/lessons', [TutorRemedialAssignmentController::class, 'addLesson'])->name('tutor.remedial-lessons.store');
    Route::delete('tutor/remedials/{remedialAssignment}/lessons/{item}', [TutorRemedialAssignmentController::class, 'removeLesson'])->name('tutor.remedial-lessons.destroy');

    Route::get('student/classes', [StudentLearningClassController::class, 'index'])
        ->name('student.classes.index');
    Route::get('student/progress', StudentProgressInsightController::class)
        ->name('student.progress.index');
    Route::get('student/classes/{learningClass}', [StudentLearningClassController::class, 'show'])
        ->name('student.classes.show');
    Route::get('student/classes/{learningClass}/assessments', [StudentAssessmentController::class, 'index'])
        ->name('student.assessments.index');
    Route::get('student/classes/{learningClass}/assessments/{assignment}', [StudentAssessmentController::class, 'show'])
        ->name('student.assessments.show');
    Route::post('student/classes/{learningClass}/assessments/{assignment}/start', [StudentAssessmentController::class, 'start'])
        ->middleware('throttle:assessment-start')
        ->name('student.assessments.start');
    Route::get('student/assessment-attempts/{attempt}', [StudentAssessmentAttemptController::class, 'show'])
        ->name('student.assessment-attempts.show');
    Route::patch('student/assessment-attempts/{attempt}/questions/{attemptQuestion}/answer', [StudentAssessmentAnswerController::class, 'update'])
        ->middleware('throttle:assessment-answer')
        ->name('student.assessment-answers.update');
    Route::post('student/assessment-attempts/{attempt}/submit', [StudentAssessmentAttemptController::class, 'submit'])
        ->middleware('throttle:assessment-submit')
        ->name('student.assessment-attempts.submit');
    Route::get('student/assessment-attempts/{attempt}/result', [StudentAssessmentAttemptController::class, 'result'])
        ->name('student.assessment-attempts.result');
    Route::get('student/assessment-attempts/{attempt}/questions/{attemptQuestion}/image', [StudentAssessmentAttemptImageController::class, 'show'])
        ->name('student.attempt-question-images.show');
    Route::get('student/remedials/{remedialAssignment}', [StudentRemedialAssignmentController::class, 'show'])
        ->name('student.remedials.show');
    Route::patch('student/remedials/{remedialAssignment}/lessons/{item}/complete', [StudentRemedialAssignmentController::class, 'completeLesson'])
        ->name('student.remedial-lessons.complete');
    Route::get('student/classes/{learningClass}/lessons/{lesson}', [StudentLessonController::class, 'show'])
        ->name('student.lessons.show');
    Route::get('student/classes/{learningClass}/lessons/{lesson}/file', [StudentLessonController::class, 'file'])
        ->name('student.lessons.file');
    Route::get('student/classes/{learningClass}/lessons/{lesson}/assets/{asset}/file', [StudentLessonAssetController::class, 'file'])
        ->name('student.lesson-assets.file');
    Route::post('student/classes/{learningClass}/lessons/{lesson}/checkpoints/{checkpoint}/submit', [StudentLessonCheckpointController::class, 'store'])
        ->middleware('throttle:lesson-checkpoint')
        ->name('student.lesson-checkpoints.store');
    Route::patch('student/classes/{learningClass}/lessons/{lesson}/progress', [StudentLessonProgressController::class, 'update'])
        ->name('student.lesson-progress.update');
});

require __DIR__.'/settings.php';
