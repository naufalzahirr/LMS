<?php

namespace Tests\Feature\Notifications;

use App\Models\RemedialAssignment;
use App\Models\User;
use App\Notifications\RemedialAssignedNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_list_own_notifications(): void
    {
        $user = $this->userWithRole('Student');
        $this->notify($user);
        $this->notify($this->userWithRole('Student'));

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/Index')
                ->has('notifications.data', 1)
                ->where('unreadCount', 1));
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = $this->userWithRole('Student');
        $other = $this->userWithRole('Student');
        $notification = $this->notify($owner);

        $this->actingAs($other)->patch(route('notifications.read', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_new_notification_starts_unread(): void
    {
        $user = $this->userWithRole('Student');
        $this->notify($user);

        $this->assertSame(1, $user->unreadNotifications()->count());
    }

    public function test_marking_one_notification_read_decrements_unread_count(): void
    {
        $user = $this->userWithRole('Student');
        $notification = $this->notify($user);
        $this->notify($user);

        $this->actingAs($user)->patch(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertSame(1, $user->unreadNotifications()->count());
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marking_an_already_read_notification_is_idempotent(): void
    {
        $user = $this->userWithRole('Student');
        $notification = $this->notify($user);

        $this->actingAs($user)->patch(route('notifications.read', $notification))->assertRedirect();
        $firstReadAt = $notification->fresh()->read_at;

        $this->actingAs($user)->patch(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertTrue($firstReadAt->equalTo($notification->fresh()->read_at));
    }

    public function test_mark_all_read_only_affects_the_current_users_notifications(): void
    {
        $user = $this->userWithRole('Student');
        $otherUser = $this->userWithRole('Student');
        $this->notify($user);
        $this->notify($user);
        $otherNotification = $this->notify($otherUser);

        $this->actingAs($user)->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $otherUser->unreadNotifications()->count());
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_notification_payload_never_exposes_answer_key_shaped_data(): void
    {
        $user = $this->userWithRole('Student');
        $this->notify($user);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk()
            ->assertDontSee('correct_option_ids')
            ->assertDontSee('accepted_answers')
            ->assertDontSee('correct_boolean')
            ->assertDontSee('answer_key');
    }

    public function test_shared_notification_payload_uses_a_bounded_number_of_queries(): void
    {
        $user = $this->userWithRole('Student');
        $this->notify($user);
        $this->notify($user);
        $this->notify($user);

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('dashboard'));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(30, $queryCount);
    }

    private function notify(User $user): DatabaseNotification
    {
        $user->notify(new RemedialAssignedNotification(RemedialAssignment::factory()->create()));

        return $user->notifications()->latest()->firstOrFail();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
