<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicStatus;
use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\Question;
use App\Models\QuestionAcceptedAnswer;
use App\Models\QuestionOption;
use App\Models\User;
use App\Services\AssessmentAuthoringOptionsService;
use App\Services\QuestionService;
use App\Services\TutorCourseAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestionController extends Controller
{
    public function __construct(
        private readonly QuestionService $service,
        private readonly AssessmentAuthoringOptionsService $options,
        private readonly TutorCourseAccessService $tutorAccess,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Question::class);
        $search = trim($request->string('search')->toString());
        $programId = $request->integer('program_id');
        $courseId = $request->integer('course_id');
        $competencyId = $request->integer('competency_id');
        $bankId = $request->integer('question_bank_id');
        $type = QuestionType::tryFrom($request->string('question_type')->toString());
        $status = AcademicStatus::tryFrom($request->string('status')->toString());
        $query = Question::query()->with(['questionBank.course.program:id,name', 'competency:id,course_id,name']);
        $user = $this->user($request);

        if ($user->hasRole('Tutor')) {
            $courseIds = $this->tutorAccess->manageableCourseIds($user);
            $query->whereHas('questionBank', fn ($query) => $query->whereIn('course_id', $courseIds));
        }
        if ($search !== '') {
            $query->where('prompt', 'like', "%{$search}%");
        }
        if ($programId > 0) {
            $query->whereHas('questionBank.course', fn ($query) => $query->where('program_id', $programId));
        }
        if ($courseId > 0) {
            $query->whereHas('questionBank', fn ($query) => $query->where('course_id', $courseId));
        }
        if ($competencyId > 0) {
            $query->where('competency_id', $competencyId);
        }
        if ($bankId > 0) {
            $query->where('question_bank_id', $bankId);
        }
        if ($type !== null) {
            $query->where('question_type', $type->value);
        }
        if ($status !== null) {
            $query->where('status', $status->value);
        }
        $paginator = $query->orderBy('question_bank_id')->orderBy('sort_order')->paginate(10)->withQueryString();

        return Inertia::render('admin/questions/Index', [
            'questions' => [
                'data' => $paginator->getCollection()->map(fn (Question $question): array => [
                    'id' => $question->id, 'prompt' => $question->prompt, 'question_type' => $question->question_type->value,
                    'competency' => $question->competency->name, 'course' => $question->questionBank->course->name,
                    'program' => $question->questionBank->course->program->name, 'bank' => $question->questionBank->name,
                    'default_points' => $question->default_points, 'status' => $question->status->value,
                    'can_update' => $user->can('update', $question), 'can_delete' => $user->can('delete', $question),
                ])->all(),
                'links' => $paginator->linkCollection()->all(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total(),
            ],
            'filters' => ['search' => $search, 'program_id' => $programId > 0 ? (string) $programId : '', 'course_id' => $courseId > 0 ? (string) $courseId : '', 'competency_id' => $competencyId > 0 ? (string) $competencyId : '', 'question_bank_id' => $bankId > 0 ? (string) $bankId : '', 'question_type' => $type->value ?? '', 'status' => $status->value ?? ''],
            ...$this->options->forUser($user), 'questionTypes' => QuestionType::options(), 'statuses' => AcademicStatus::options(),
            'canManage' => $user->can('create', Question::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Question::class);

        return Inertia::render('admin/questions/Create', [...$this->options->forUser($this->user($request)), 'questionTypes' => QuestionType::options(), 'statuses' => AcademicStatus::options()]);
    }

    public function store(StoreQuestionRequest $request): RedirectResponse
    {
        $question = $this->service->create($request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question created.')]);

        return to_route('admin.questions.show', $question);
    }

    public function show(Question $question): Response
    {
        $this->authorize('view', $question);
        $question->load(['questionBank.course.program', 'competency', 'options', 'acceptedAnswers']);

        return Inertia::render('admin/questions/Show', ['question' => $this->authoringQuestion($question), 'canManage' => request()->user()?->can('update', $question) ?? false]);
    }

    public function edit(Request $request, Question $question): Response
    {
        $this->authorize('update', $question);
        $question->load(['options', 'acceptedAnswers']);

        return Inertia::render('admin/questions/Edit', ['question' => $this->authoringQuestion($question), ...$this->options->forUser($this->user($request)), 'questionTypes' => QuestionType::options(), 'statuses' => AcademicStatus::options()]);
    }

    public function update(UpdateQuestionRequest $request, Question $question): RedirectResponse
    {
        $this->service->update($question, $request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question updated.')]);

        return to_route('admin.questions.show', $question);
    }

    public function destroy(Question $question): RedirectResponse
    {
        $this->authorize('delete', $question);
        $this->service->delete($question);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question deleted.')]);

        return to_route('admin.questions.index');
    }

    /** @return array<string, mixed> */
    private function authoringQuestion(Question $question): array
    {
        return [
            'id' => $question->id, 'question_bank_id' => $question->question_bank_id, 'competency_id' => $question->competency_id,
            'question_type' => $question->question_type->value, 'prompt' => $question->prompt, 'explanation' => $question->explanation,
            'default_points' => $question->default_points, 'correct_boolean' => $question->correct_boolean,
            'status' => $question->status->value, 'sort_order' => $question->sort_order,
            'bank' => $question->relationLoaded('questionBank') ? $question->questionBank->name : null,
            'competency' => $question->relationLoaded('competency') ? $question->competency->name : null,
            'course' => $question->relationLoaded('questionBank') ? $question->questionBank->course->name : null,
            'program' => $question->relationLoaded('questionBank') ? $question->questionBank->course->program->name : null,
            'options' => $question->options->map(fn (QuestionOption $option): array => ['id' => $option->id, 'option_text' => $option->option_text, 'is_correct' => $option->is_correct, 'sort_order' => $option->sort_order])->all(),
            'accepted_answers' => $question->acceptedAnswers->map(fn (QuestionAcceptedAnswer $answer): array => ['id' => $answer->id, 'answer_text' => $answer->answer_text, 'case_sensitive' => $answer->case_sensitive])->all(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
