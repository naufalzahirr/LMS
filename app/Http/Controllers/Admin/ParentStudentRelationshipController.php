<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ParentRelationshipType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreParentStudentRelationshipRequest;
use App\Models\ParentStudentRelationship;
use App\Models\User;
use App\Services\ParentStudentRelationshipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParentStudentRelationshipController extends Controller
{
    public function __construct(private readonly ParentStudentRelationshipService $relationshipService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ParentStudentRelationship::class);

        $search = trim($request->string('search')->toString());
        $type = ParentRelationshipType::tryFrom($request->string('relationship_type')->toString());
        $query = ParentStudentRelationship::query()->with(['parent:id,name,email', 'student:id,name,email']);

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->whereHas('parent', fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('student', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($type !== null) {
            $query->where('relationship_type', $type->value);
        }

        $paginator = $query->latest()->paginate(10)->withQueryString();

        return Inertia::render('admin/parent-students/Index', [
            'relationships' => [
                'data' => $paginator->getCollection()->map(fn (ParentStudentRelationship $relationship): array => [
                    'id' => $relationship->id,
                    'parent' => ['name' => $relationship->parent->name, 'email' => $relationship->parent->email],
                    'student' => ['name' => $relationship->student->name, 'email' => $relationship->student->email],
                    'relationship_type' => $relationship->relationship_type->value,
                    'relationship_label' => $relationship->relationship_type->label(),
                ])->all(),
                'links' => $paginator->linkCollection()->all(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'search' => $search,
                'relationship_type' => $type->value ?? '',
            ],
            'relationshipTypes' => ParentRelationshipType::options(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ParentStudentRelationship::class);

        return Inertia::render('admin/parent-students/Create', [
            'parents' => $this->userOptions('Parent'),
            'students' => $this->userOptions('Student'),
            'relationshipTypes' => ParentRelationshipType::options(),
        ]);
    }

    public function store(StoreParentStudentRelationshipRequest $request): RedirectResponse
    {
        $this->relationshipService->create(
            $request->parent(),
            $request->student(),
            $request->relationshipType(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Parent and student linked.')]);

        return to_route('admin.parent-students.index');
    }

    public function destroy(ParentStudentRelationship $parentStudent): RedirectResponse
    {
        $this->authorize('delete', $parentStudent);
        $this->relationshipService->delete($parentStudent);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Relationship removed.')]);

        return to_route('admin.parent-students.index');
    }

    /**
     * @return array<int, array{id: int, name: string, email: string}>
     */
    private function userOptions(string $role): array
    {
        return User::role($role)->orderBy('name')->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->all();
    }
}
