<?php

namespace Tests\Feature\Performance;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\FlowRiveSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A page that quietly grows from 8 queries to 80 still returns 200, so the
 * count is asserted rather than eyeballed. Budgets sit a little above today's
 * numbers — they catch N+1 regressions, not normal drift.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_page_exceeds_its_query_budget()
    {
        $this->seed(FlowRiveSeeder::class);

        // Priya is the workspace admin, so every page is reachable.
        $user = User::where('email', 'priya.nair@flowrive.test')->firstOrFail();

        // Ids are looked up rather than hardcoded — auto-increment does not
        // reset between tests in the same run.
        $project = Project::where('key', 'WEB')->firstOrFail();
        $task = Task::where('title', 'Design homepage hero section')->firstOrFail();
        $member = User::where('email', 'sarah.wilson@flowrive.test')->firstOrFail();

        $budgets = [
            '/dashboard' => 25,
            '/projects' => 15,
            '/projects/create' => 12,
            '/tasks' => 20,
            '/tasks/create' => 15,
            "/tasks/{$task->id}" => 25,
            "/tasks/{$task->id}/edit" => 15,
            "/projects/{$project->id}/board" => 20,
            "/projects/{$project->id}/edit" => 15,
            '/team' => 20,
            "/team/{$member->id}" => 20,
            '/notifications' => 15,
        ];

        foreach ($budgets as $path => $budget) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $response = $this->actingAs($user)->get($path);

            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            $this->assertSame(200, $response->status(), "{$path} did not return 200");
            $this->assertLessThanOrEqual(
                $budget,
                $queries,
                "{$path} ran {$queries} queries, over its budget of {$budget}. Look for an N+1.",
            );
        }
    }
}
