<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionBankRequest;
use App\Http\Requests\Admin\UpdateQuestionBankRequest;
use App\Models\QuestionBank;
use App\Models\User;
use App\Services\AssessmentAuthoringOptionsService;
use App\Services\QuestionBankService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestionBankController extends Controller
{
    public function __construct(
        private readonly QuestionBankService $service,
        private readonly AssessmentAuthoringOptionsService $options,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', QuestionBank::class);
        $search = trim($request->string('search')->toString());
        $programId = $request->integer('program_id');
        $courseId = $request->integer('course_id');
        $status = AcademicStatus::tryFrom($request->string('status')->toString());
        $query = QuestionBank::query()->with('course.program:id,name')->withCount('questions');

        if ($search !== '') {
            $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
        }
        if ($programId > 0) {
            $query->whereHas('course', fn ($query) => $query->where('program_id', $programId));
        }
        if ($courseId > 0) {
            $query->where('course_id', $courseId);
        }
        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $paginator = $query->orderBy('name')->paginate(10)->withQueryString();
        $user = $this->user($request);
        $authoringOptions = $this->options->forUser($user, false);

        return Inertia::render('admin/question-banks/Index', [
            'questionBanks' => [
                'data' => $paginator->getCollection()->map(fn (QuestionBank $bank): array => [
                    'id' => $bank->id,
                    'name' => $bank->name,
                    'code' => $bank->code,
                    'course' => $bank->course->name,
                    'program' => $bank->course->program->name,
                    'questions_count' => $bank->questions_count ?? 0,
                    'status' => $bank->status->value,
                    'can_update' => $user->can('update', $bank),
                    'can_delete' => $user->can('delete', $bank),
                ])->all(),
                'links' => $paginator->linkCollection()->all(),
                'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total(),
            ],
            'filters' => ['search' => $search, 'program_id' => $programId > 0 ? (string) $programId : '', 'course_id' => $courseId > 0 ? (string) $courseId : '', 'status' => $status->value ?? ''],
            'programs' => $authoringOptions['programs'],
            'courses' => $authoringOptions['courses'],
            'statuses' => AcademicStatus::options(),
            'canManage' => $user->can('create', QuestionBank::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', QuestionBank::class);

        return Inertia::render('admin/question-banks/Create', [...$this->options->forUser($this->user($request)), 'statuses' => AcademicStatus::options()]);
    }

    public function store(StoreQuestionBankRequest $request): RedirectResponse
    {
        $this->service->create($request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question bank created.')]);

        return to_route('admin.question-banks.index');
    }

    public function edit(Request $request, QuestionBank $questionBank): Response
    {
        $this->authorize('update', $questionBank);

        return Inertia::render('admin/question-banks/Edit', [
            'questionBank' => ['id' => $questionBank->id, 'course_id' => $questionBank->course_id, 'name' => $questionBank->name, 'code' => $questionBank->code, 'description' => $questionBank->description, 'status' => $questionBank->status->value],
            ...$this->options->forUser($this->user($request)), 'statuses' => AcademicStatus::options(),
        ]);
    }

    public function update(UpdateQuestionBankRequest $request, QuestionBank $questionBank): RedirectResponse
    {
        $this->service->update($questionBank, $request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question bank updated.')]);

        return to_route('admin.question-banks.index');
    }

    public function destroy(QuestionBank $questionBank): RedirectResponse
    {
        $this->authorize('delete', $questionBank);
        $this->service->delete($questionBank);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question bank deleted.')]);

        return to_route('admin.question-banks.index');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
