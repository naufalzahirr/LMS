<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicStatus;
use App\Enums\AssessmentPurpose;
use App\Enums\AssessmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAssessmentRequest;
use App\Http\Requests\Admin\UpdateAssessmentRequest;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\Question;
use App\Models\User;
use App\Services\AssessmentAuthoringOptionsService;
use App\Services\AssessmentService;
use App\Services\TutorCourseAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function __construct(
        private readonly AssessmentService $service,
        private readonly AssessmentAuthoringOptionsService $options,
        private readonly TutorCourseAccessService $tutorAccess,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Assessment::class);
        $search = trim($request->string('search')->toString());
        $programId = $request->integer('program_id');
        $courseId = $request->integer('course_id');
        $competencyId = $request->integer('competency_id');
        $purpose = AssessmentPurpose::tryFrom($request->string('purpose')->toString());
        $status = AssessmentStatus::tryFrom($request->string('status')->toString());
        $query = Assessment::query()
            ->with('competency.course.program:id,name')
            ->withCount('assessmentQuestions')
            ->withSum('assessmentQuestions as total_points', 'points');
        $user = $this->user($request);

        if ($user->hasRole('Tutor')) {
            $query->whereHas('competency', fn ($query) => $query->whereIn('course_id', $this->tutorAccess->manageableCourseIds($user)));
        }

        if ($search !== '') {
            $query->where(fn ($query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        if ($programId > 0) {
            $query->whereHas('competency.course', fn ($query) => $query->where('program_id', $programId));
        }

        if ($courseId > 0) {
            $query->whereHas('competency', fn ($query) => $query->where('course_id', $courseId));
        }

        if ($competencyId > 0) {
            $query->where('competency_id', $competencyId);
        }

        if ($purpose !== null) {
            $query->where('purpose', $purpose->value);
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $paginator = $query->orderByDesc('updated_at')->paginate(10)->withQueryString();

        return Inertia::render('admin/assessments/Index', [
            'assessments' => [
                'data' => $paginator->getCollection()->map(fn (Assessment $assessment): array => [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'code' => $assessment->code,
                    'purpose' => $assessment->purpose->value,
                    'status' => $assessment->status->value,
                    'competency' => $assessment->competency->name,
                    'course' => $assessment->competency->course->name,
                    'program' => $assessment->competency->course->program->name,
                    'questions_count' => $assessment->assessment_questions_count ?? 0,
                    'total_points' => $assessment->getAttribute('total_points') ?? '0.00',
                    'can_update' => $user->can('update', $assessment),
                    'can_delete' => $user->can('delete', $assessment),
                ])->all(),
                'links' => $paginator->linkCollection()->all(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'search' => $search,
                'program_id' => $programId > 0 ? (string) $programId : '',
                'course_id' => $courseId > 0 ? (string) $courseId : '',
                'competency_id' => $competencyId > 0 ? (string) $competencyId : '',
                'purpose' => $purpose->value ?? '',
                'status' => $status->value ?? '',
            ],
            ...$this->options->forUser($user),
            'purposes' => AssessmentPurpose::options(),
            'statuses' => AssessmentStatus::options(),
            'canManage' => $user->can('create', Assessment::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Assessment::class);

        return Inertia::render('admin/assessments/Create', [
            ...$this->options->forUser($this->user($request)),
            'purposes' => AssessmentPurpose::options(),
        ]);
    }

    public function store(StoreAssessmentRequest $request): RedirectResponse
    {
        $assessment = $this->service->create($request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assessment created as a draft.')]);

        return to_route('admin.assessments.show', $assessment);
    }

    public function show(Request $request, Assessment $assessment): Response
    {
        $this->authorize('view', $assessment);
        $assessment->load([
            'competency.course.program',
            'assessmentQuestions.question.questionBank:id,name',
        ]);
        $attachedQuestionIds = $assessment->assessmentQuestions->pluck('question_id');
        $candidates = Question::query()
            ->where('competency_id', $assessment->competency_id)
            ->where('status', AcademicStatus::Active->value)
            ->whereNotIn('id', $attachedQuestionIds)
            ->with('questionBank:id,name')
            ->orderBy('question_bank_id')
            ->orderBy('sort_order')
            ->get();
        $user = $this->user($request);

        return Inertia::render('admin/assessments/Show', [
            'assessment' => $this->assessmentDetails($assessment),
            'questions' => $assessment->assessmentQuestions->map(fn (AssessmentQuestion $item): array => [
                'id' => $item->id,
                'question_id' => $item->question_id,
                'prompt' => $item->question->prompt,
                'question_type' => $item->question->question_type->value,
                'bank' => $item->question->questionBank->name,
                'points' => $item->points,
                'status' => $item->question->status->value,
            ])->values()->all(),
            'questionOptions' => $candidates->map(fn (Question $question): array => [
                'id' => $question->id,
                'label' => $question->prompt,
                'type' => $question->question_type->value,
                'bank' => $question->questionBank->name,
                'default_points' => $question->default_points,
            ])->all(),
            'canManage' => $user->can('update', $assessment) && $assessment->status !== AssessmentStatus::Archived,
        ]);
    }

    public function edit(Request $request, Assessment $assessment): Response
    {
        $this->authorize('update', $assessment);

        return Inertia::render('admin/assessments/Edit', [
            'assessment' => $this->assessmentDetails($assessment),
            ...$this->options->forUser($this->user($request)),
            'purposes' => AssessmentPurpose::options(),
        ]);
    }

    public function update(UpdateAssessmentRequest $request, Assessment $assessment): RedirectResponse
    {
        $this->service->update($assessment, $request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assessment updated.')]);

        return to_route('admin.assessments.show', $assessment);
    }

    public function publish(Assessment $assessment): RedirectResponse
    {
        $this->authorize('update', $assessment);
        $this->service->publish($assessment);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assessment published.')]);

        return back();
    }

    public function archive(Assessment $assessment): RedirectResponse
    {
        $this->authorize('update', $assessment);
        $this->service->archive($assessment);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assessment archived.')]);

        return back();
    }

    public function destroy(Assessment $assessment): RedirectResponse
    {
        $this->authorize('delete', $assessment);
        $this->service->delete($assessment);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assessment deleted.')]);

        return to_route('admin.assessments.index');
    }

    /** @return array<string, mixed> */
    private function assessmentDetails(Assessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'competency_id' => $assessment->competency_id,
            'title' => $assessment->title,
            'code' => $assessment->code,
            'description' => $assessment->description,
            'purpose' => $assessment->purpose->value,
            'status' => $assessment->status->value,
            'instructions' => $assessment->instructions,
            'shuffle_questions' => $assessment->shuffle_questions,
            'competency' => $assessment->relationLoaded('competency') ? $assessment->competency->name : null,
            'course' => $assessment->relationLoaded('competency') ? $assessment->competency->course->name : null,
            'program' => $assessment->relationLoaded('competency') ? $assessment->competency->course->program->name : null,
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
