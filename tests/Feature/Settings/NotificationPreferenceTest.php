<?php

namespace Tests\Feature\Settings;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_matrix_is_backfilled_for_an_account_that_predates_an_event()
    {
        $user = User::factory()->create();
        $this->assertSame(0, $user->notificationPreferences()->count());

        $this->actingAs($user)
            ->get(route('notification-preferences.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/notifications')
                ->has('preferences', count(NotificationPreference::DEFAULTS))
                ->where('preferences.0.event', 'task_assigned')
            );
    }

    public function test_saving_the_matrix_persists_every_channel()
    {
        $user = User::factory()->create();
        $user->applyDefaultNotificationPreferences();

        $this->actingAs($user)->put(route('notification-preferences.update'), [
            'preferences' => [
                ['event' => 'task_assigned', 'inApp' => false, 'email' => false, 'push' => false],
                ['event' => 'mentioned', 'inApp' => true, 'email' => true, 'push' => true],
            ],
            'digest' => ['frequency' => 'weekly', 'quietFrom' => '19:00', 'quietTo' => '08:00'],
        ])->assertRedirect();

        $assigned = $user->notificationPreferences()->where('event', 'task_assigned')->firstOrFail();
        $this->assertFalse($assigned->in_app);
        $this->assertFalse($assigned->email);

        $this->assertSame('weekly', $user->fresh()->digest_frequency);
    }

    public function test_an_unknown_event_from_the_client_is_ignored()
    {
        $user = User::factory()->create();
        $user->applyDefaultNotificationPreferences();
        $before = $user->notificationPreferences()->count();

        $this->actingAs($user)->put(route('notification-preferences.update'), [
            'preferences' => [
                ['event' => 'made_up_event', 'inApp' => true, 'email' => true, 'push' => true],
            ],
            'digest' => ['frequency' => 'daily'],
        ])->assertRedirect();

        $this->assertSame($before, $user->notificationPreferences()->count());
    }
}
