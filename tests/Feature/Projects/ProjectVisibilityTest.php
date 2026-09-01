<?php

namespace Tests\Feature\Projects;

use App\Enums\MemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The two-layer role model is the part most likely to leak, so it gets tests
 * of its own: a Member must not see a project they were never added to.
 */
class ProjectVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_only_sees_projects_they_belong_to()
    {
        [$workspace, $admin] = $this->workspace();
        $member = $this->member($workspace, WorkspaceRole::Member);

        $mine = $this->project($workspace, $admin, 'MIN', 'Mine');
        $mine->members()->attach($member->id, ['role' => 'member']);

        $this->project($workspace, $admin, 'OTH', 'Not mine');

        $this->actingAs($member)
            ->get(route('projects.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('projects', 1)
                ->where('projects.0.name', 'Mine')
            );
    }

    public function test_an_admin_sees_every_project_without_a_membership_row()
    {
        [$workspace, $admin] = $this->workspace();
        $this->project($workspace, $admin, 'ONE', 'First');
        $this->project($workspace, $admin, 'TWO', 'Second');

        $this->actingAs($admin)
            ->get(route('projects.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('projects', 2));
    }

    public function test_workspace_visible_projects_are_open_to_anyone_with_a_seat()
    {
        [$workspace, $admin] = $this->workspace();
        $member = $this->member($workspace, WorkspaceRole::Member);

        $this->project($workspace, $admin, 'OPN', 'Open to all')
            ->update(['visibility' => 'workspace']);

        $this->actingAs($member)
            ->get(route('projects.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('projects', 1)
                ->where('projects.0.name', 'Open to all')
            );
    }

    public function test_opening_the_board_of_an_unrelated_project_is_forbidden()
    {
        [$workspace, $admin] = $this->workspace();
        $member = $this->member($workspace, WorkspaceRole::Member);
        $project = $this->project($workspace, $admin, 'SEC', 'Secret');

        $this->actingAs($member)
            ->get(route('projects.board', $project))
            ->assertForbidden();
    }

    public function test_search_and_status_filters_narrow_the_list()
    {
        [$workspace, $admin] = $this->workspace();
        $this->project($workspace, $admin, 'WEB', 'Website Redesign');
        $this->project($workspace, $admin, 'MOB', 'Mobile App')
            ->update(['status' => 'completed']);

        $this->actingAs($admin)
            ->get(route('projects.index', ['search' => 'website']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('projects', 1)
                ->where('projects.0.key', 'WEB')
            );

        $this->actingAs($admin)
            ->get(route('projects.index', ['status' => ['completed']]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('projects', 1)
                ->where('projects.0.key', 'MOB')
            );
    }

    /** @return array{0: Workspace, 1: User} */
    private function workspace(): array
    {
        $admin = User::factory()->create();

        $workspace = Workspace::create([
            'name' => 'Northwind Studio',
            'slug' => 'northwind-'.uniqid(),
            'owner_id' => $admin->id,
        ]);

        $workspace->members()->create([
            'user_id' => $admin->id,
            'role' => WorkspaceRole::Admin,
            'status' => MemberStatus::Active,
            'joined_at' => now(),
        ]);

        return [$workspace, $admin];
    }

    private function member(Workspace $workspace, WorkspaceRole $role): User
    {
        $user = User::factory()->create();

        $workspace->members()->create([
            'user_id' => $user->id,
            'role' => $role,
            'status' => MemberStatus::Active,
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function project(Workspace $workspace, User $owner, string $key, string $name): Project
    {
        $project = $workspace->projects()->create([
            'key' => $key,
            'name' => $name,
            'lead_id' => $owner->id,
            'created_by' => $owner->id,
        ]);

        $project->createDefaultColumns();

        return $project;
    }
}
