<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NotificationSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly NotificationSummaryService $notifications) {}

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $paginator = $user->notifications()->latest()->paginate(self::PER_PAGE);

        return Inertia::render('notifications/Index', [
            // Named distinctly from the global "notificationSummary" shared
            // prop so this page's paginated collection can never overwrite it.
            'notificationPage' => [
                'data' => $paginator->getCollection()
                    ->map(fn (DatabaseNotification $notification): array => $this->notifications->mapForDisplay($notification))
                    ->all(),
                'links' => $paginator->linkCollection()->all(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'unreadCount' => $user->unreadNotifications()->count(),
            'markAllReadUrl' => route('notifications.read-all'),
        ]);
    }

    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($notification->notifiable_id === $user->id && $notification->notifiable_type === $user::class, 403);
        $notification->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
