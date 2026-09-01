<?php

namespace Tests\Feature\Team;

use App\Enums\MemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_roster_shows_role_and_status_from_the_membership()
    {
        [$admin, $workspace] = $this->workspace();
        $this->seat($workspace, WorkspaceRole::Member, MemberStatus::Invited);

        $this->actingAs($admin)
            ->get(route('team.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('team/index')
                ->has('members', 2)
                ->where('members.0.isYou', true)
                ->where('members.1.statusLabel', 'Invited')
                ->where('canInvite', true)
            );
    }

    public function test_status_lives_on_the_membership_so_the_same_person_can_differ_per_workspace()
    {
        [$admin, $workspace] = $this->workspace();
        $person = $this->seat($workspace, WorkspaceRole::Member, MemberStatus::Active);

        // The same user, invited but not yet active in a second workspace
        $second = Workspace::create([
            'name' => 'Other', 'slug' => 'other-'.uniqid(), 'owner_id' => $admin->id,
        ]);
        $second->members()->create([
            'user_id' => $person->id, 'role' => WorkspaceRole::Member,
            'status' => MemberStatus::Invited,
        ]);

        $this->actingAs($admin)
            ->get(route('team.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('members.1.statusLabel', 'Active')
            );
    }

    public function test_the_member_page_reports_their_work()
    {
        [$admin, $workspace] = $this->workspace();
        $person = $this->seat($workspace, WorkspaceRole::Member, MemberStatus::Active);

        $this->actingAs($admin)
            ->get(route('team.show', $person))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('team/show')
                ->where('member.name', $person->name)
                ->has('stats')
                ->has('projects')
                ->has('tasks')
            );
    }

    public function test_someone_outside_the_workspace_is_not_found()
    {
        [$admin] = $this->workspace();
        $stranger = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('team.show', $stranger))
            ->assertNotFound();
    }

    public function test_a_manager_can_invite_but_a_member_cannot()
    {
        [, $workspace] = $this->workspace();
        $manager = $this->seat($workspace, WorkspaceRole::Manager, MemberStatus::Active);
        $member = $this->seat($workspace, WorkspaceRole::Member, MemberStatus::Active);

        $this->actingAs($member)
            ->post(route('invitations.store'), [
                'email' => 'nope@example.com', 'role' => 'member',
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('invitations.store'), [
                'email' => 'rachel.moore@northwind.co', 'role' => 'member',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invitations', [
            'email' => 'rachel.moore@northwind.co',
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_the_same_address_cannot_be_invited_twice_while_one_is_pending()
    {
        [$admin, $workspace] = $this->workspace();

        Invitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'taken@example.com',
            'role' => WorkspaceRole::Member,
            'token' => Invitation::freshToken(),
            'invited_by' => $admin->id,
            'expires_at' => now()->addWeek(),
        ]);

        $this->actingAs($admin)
            ->post(route('invitations.store'), [
                'email' => 'taken@example.com', 'role' => 'member',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_revoking_an_invitation_removes_it_from_the_pending_list()
    {
        [$admin, $workspace] = $this->workspace();

        $invitation = Invitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'bye@example.com',
            'role' => WorkspaceRole::Member,
            'token' => Invitation::freshToken(),
            'invited_by' => $admin->id,
            'expires_at' => now()->addWeek(),
        ]);

        $this->actingAs($admin)
            ->delete(route('invitations.destroy', $invitation))
            ->assertRedirect();

        $this->assertNotNull($invitation->fresh()->revoked_at);

        $this->actingAs($admin)
            ->get(route('team.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('invitations', 0));
    }

    /** @return array{0: User, 1: Workspace} */
    private function workspace(): array
    {
        $admin = User::factory()->create();

        $workspace = Workspace::create([
            'name' => 'Northwind Studio',
            'slug' => 'northwind-'.uniqid(),
            'owner_id' => $admin->id,
        ]);
        $workspace->members()->create([
            'user_id' => $admin->id, 'role' => WorkspaceRole::Admin,
            'status' => MemberStatus::Active, 'joined_at' => now(),
        ]);

        return [$admin, $workspace];
    }

    private function seat(Workspace $workspace, WorkspaceRole $role, MemberStatus $status): User
    {
        $user = User::factory()->create();

        $workspace->members()->create([
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
            'joined_at' => $status === MemberStatus::Active ? now() : null,
        ]);

        return $user;
    }
}
