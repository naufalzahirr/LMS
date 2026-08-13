<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\NotificationSummaryService;
use App\Services\TutorCourseAccessService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly TutorCourseAccessService $tutorAccess,
        private readonly NotificationSummaryService $notifications,
    ) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'roles' => $user?->getRoleNames()->values() ?? [],
                'permissions' => $user?->getAllPermissions()->pluck('name')->values() ?? [],
                'has_active_teaching_course' => $user instanceof User
                    && $this->tutorAccess->hasAnyActiveCourse($user),
            ],
            // Distinct from the Notification Center page's own paginated
            // "notificationPage" prop — sharing a name would let the page
            // prop silently overwrite this global summary in page.props.
            'notificationSummary' => $user instanceof User
                ? $this->notifications->sharedPayload($user)
                : ['unread_count' => 0, 'latest' => []],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
