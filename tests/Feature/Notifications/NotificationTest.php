<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_centre_lists_your_notifications_with_per_category_counts()
    {
        $user = User::factory()->create();
        $this->notify($user, 'mention', read: false);
        $this->notify($user, 'task', read: true);
        $this->notify($user, 'task', read: false);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('notifications/index')
                ->has('notifications', 3)
                ->where('counts.all', 3)
                ->where('counts.unread', 2)
                ->where('counts.task', 2)
                ->where('counts.mention', 1)
            );
    }

    public function test_you_never_see_someone_elses_notifications()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->notify($other, 'mention', read: false);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('notifications', 0));
    }

    public function test_notifications_older_than_the_retention_window_are_not_listed()
    {
        $user = User::factory()->create();
        $id = $this->notify($user, 'system', read: true);

        DB::table('notifications')
            ->where('id', $id)
            ->update(['created_at' => now()->subDays(31)]);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('notifications', 0));
    }

    public function test_the_category_and_unread_filters_narrow_the_list()
    {
        $user = User::factory()->create();
        $this->notify($user, 'mention', read: false);
        $this->notify($user, 'system', read: true);

        $this->actingAs($user)
            ->get(route('notifications.index', ['category' => 'mention']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('notifications', 1));

        $this->actingAs($user)
            ->get(route('notifications.index', ['unread' => 1]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('notifications', 1)
                ->where('notifications.0.category', 'mention')
            );
    }

    public function test_marking_one_read_and_then_unread_round_trips()
    {
        $user = User::factory()->create();
        $id = $this->notify($user, 'task', read: false);

        $this->actingAs($user)->patch(route('notifications.update', $id), ['is_read' => true]);
        $this->assertNotNull(DB::table('notifications')->where('id', $id)->value('read_at'));

        $this->actingAs($user)->patch(route('notifications.update', $id), ['is_read' => false]);
        $this->assertNull(DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_you_cannot_mark_or_delete_a_notification_that_is_not_yours()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $id = $this->notify($other, 'task', read: false);

        $this->actingAs($user)->patch(route('notifications.update', $id), ['is_read' => true]);
        $this->assertNull(DB::table('notifications')->where('id', $id)->value('read_at'));

        $this->actingAs($user)->delete(route('notifications.destroy', $id));
        $this->assertDatabaseHas('notifications', ['id' => $id]);
    }

    public function test_mark_all_and_clear_all_only_touch_your_own()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->notify($user, 'task', read: false);
        $mine = $this->notify($user, 'mention', read: false);
        $theirs = $this->notify($other, 'task', read: false);

        $this->actingAs($user)->post(route('notifications.read-all'));
        $this->assertNotNull(DB::table('notifications')->where('id', $mine)->value('read_at'));
        $this->assertNull(DB::table('notifications')->where('id', $theirs)->value('read_at'));

        $this->actingAs($user)->delete(route('notifications.clear'));
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $user->id)->count());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $other->id)->count());
    }

    private function notify(User $user, string $category, bool $read): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\WorkspaceActivity',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'category' => $category,
            'data' => json_encode(['title' => 'Something happened', 'body' => null]),
            'read_at' => $read ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
