<?php

namespace Database\Seeders;

use App\Enums\ColumnCategory;
use App\Enums\MemberStatus;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\WorkspaceRole;
use App\Models\Activity;
use App\Models\BoardColumn;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the workspace the HTML prototype shows — Northwind Studio, its eight
 * people, eight projects and eighteen tasks.
 *
 * Keeping the same data means the React build renders exactly what the
 * mockups do, so a visual difference is a real regression rather than a
 * difference in the fixtures. Source: flowrive-ui/assets/js/data.js.
 *
 * The prototype pinned "today" to 31 Aug 2025 so its static screens always
 * showed the same mix of overdue, due-soon and upcoming work. A live database
 * has a real clock, so every date below is shifted by the gap between that
 * anchor and today — the relative story survives (WEB-118 still falls due in
 * two days) without the whole workspace going stale a year from now.
 */
class FlowRiveSeeder extends Seeder
{
    /** The date the prototype's fixtures were written against. */
    private const PROTOTYPE_TODAY = '2025-08-31';

    private Carbon $today;

    /** Days to add to every literal date so the fixtures land around today. */
    private int $offsetDays;

    private Workspace $workspace;

    /** @var array<int, User> keyed by the prototype's user id */
    private array $users = [];

    /** @var array<int, Project> keyed by the prototype's project id */
    private array $projects = [];

    /** @var array<string, Label> keyed by label name */
    private array $labels = [];

    public function run(): void
    {
        $this->today = Carbon::today()->setTime(9, 0);
        $this->offsetDays = (int) Carbon::parse(self::PROTOTYPE_TODAY)
            ->diffInDays(Carbon::today(), absolute: false);

        $this->seedUsers();
        $this->seedWorkspace();
        $this->seedLabels();
        $this->seedProjects();
        $this->seedTasks();
        $this->topUpProjects();
        $this->seedComments();
        $this->seedActivity();
        $this->seedNotifications();

        $this->command->newLine();
        $this->command->info('  Northwind Studio seeded:');
        $this->command->line('    '.User::count().' people, '
            .Project::count().' projects, '
            .Task::count().' tasks, '
            .Comment::count().' comments');
        $this->command->line('    '.DB::table('notifications')->count().' notifications for Jordan');
        $this->command->line('    Sign in as jordan.ellis@flowrive.test / password');
    }

    /* --------------------------------------------------------------- people */

    private function seedUsers(): void
    {
        $people = [
            ['id' => 1, 'name' => 'Jordan Ellis', 'title' => 'Product Manager', 'role' => WorkspaceRole::Manager, 'phone' => '+1 (415) 555-0142', 'joined' => '2023-04-11', 'status' => MemberStatus::Active, 'seen' => 2, 'dept' => 'Product',
                'bio' => 'Product manager focused on developer tooling and collaboration workflows. I care about clear specs, short feedback loops and shipping on time.'],
            ['id' => 2, 'name' => 'Sarah Wilson', 'title' => 'Design Lead', 'role' => WorkspaceRole::Manager, 'phone' => '+1 (415) 555-0117', 'joined' => '2023-01-23', 'status' => MemberStatus::Active, 'seen' => 3, 'dept' => 'Design',
                'bio' => 'Design lead. Ex-agency, now building design systems that engineers actually enjoy using.'],
            ['id' => 3, 'name' => 'Michael Chen', 'title' => 'Senior Backend Engineer', 'role' => WorkspaceRole::Member, 'phone' => '+1 (628) 555-0188', 'joined' => '2023-02-06', 'status' => MemberStatus::Active, 'seen' => 1, 'dept' => 'Engineering',
                'bio' => 'Backend engineer. Laravel, MySQL, queues and anything that needs to survive Black Friday traffic.'],
            ['id' => 4, 'name' => 'Alex Johnson', 'title' => 'Frontend Engineer', 'role' => WorkspaceRole::Member, 'phone' => '+1 (628) 555-0164', 'joined' => '2023-06-19', 'status' => MemberStatus::Active, 'seen' => 12, 'dept' => 'Engineering',
                'bio' => 'Frontend engineer working on the design system and accessibility.'],
            ['id' => 5, 'name' => 'Emily Davis', 'title' => 'Product Designer', 'role' => WorkspaceRole::Member, 'phone' => '+1 (312) 555-0193', 'joined' => '2023-09-02', 'status' => MemberStatus::Active, 'seen' => 240, 'dept' => 'Design',
                'bio' => 'Product designer. Research, prototyping and a slightly unhealthy interest in typography.'],
            ['id' => 6, 'name' => 'Daniel Smith', 'title' => 'QA Engineer', 'role' => WorkspaceRole::Member, 'phone' => '+1 (206) 555-0128', 'joined' => '2024-01-15', 'status' => MemberStatus::Active, 'seen' => 4, 'dept' => 'Engineering',
                'bio' => 'Quality engineer. If it can break on a 375px screen, I will find it.'],
            ['id' => 7, 'name' => 'Priya Nair', 'title' => 'Engineering Director', 'role' => WorkspaceRole::Admin, 'phone' => '+1 (415) 555-0106', 'joined' => '2022-11-04', 'status' => MemberStatus::Active, 'seen' => 15, 'dept' => 'Engineering',
                'bio' => 'Engineering director. Responsible for platform strategy, hiring and workspace administration.'],
            ['id' => 8, 'name' => 'Tom Becker', 'title' => 'Marketing Manager', 'role' => WorkspaceRole::Member, 'phone' => '+1 (503) 555-0175', 'joined' => '2025-08-18', 'status' => MemberStatus::Invited, 'seen' => null, 'dept' => 'Marketing',
                'bio' => 'Campaign strategy and lifecycle marketing.'],
        ];

        foreach ($people as $person) {
            $user = User::create([
                'name' => $person['name'],
                'email' => $this->emailFor($person['name']),
                'password' => Hash::make('password'),
                'email_verified_at' => $this->today,
                'job_title' => $person['title'],
                'phone' => $person['phone'],
                'bio' => $person['bio'],
                'department' => $person['dept'],
                'timezone' => 'America/Los_Angeles',
            ]);

            $user->forceFill([
                'last_seen_at' => $person['seen'] === null
                    ? null
                    : now()->subMinutes($person['seen']),
                'created_at' => $this->shift($person['joined']),
            ])->save();

            $user->applyDefaultNotificationPreferences();

            $this->users[$person['id']] = $user;
            $person['user'] = $user;
            $this->people[$person['id']] = $person;
        }
    }

    /** @var array<int, array<string, mixed>> */
    private array $people = [];

    private function emailFor(string $name): string
    {
        return str_replace(' ', '.', strtolower($name)).'@flowrive.test';
    }

    /** Shift a fixture date from the prototype's anchor onto today's calendar. */
    private function shift(string $date): Carbon
    {
        return Carbon::parse($date)->addDays($this->offsetDays);
    }

    /* ------------------------------------------------------------ workspace */

    private function seedWorkspace(): void
    {
        $this->workspace = Workspace::create([
            'name' => 'Northwind Studio',
            'slug' => 'northwind',
            'plan' => 'business',
            'seat_limit' => 25,
            'storage_limit_bytes' => 2 * 1024 * 1024 * 1024,
            'default_member_role' => WorkspaceRole::Member,
            'owner_id' => $this->users[7]->id,
        ]);

        foreach ($this->people as $person) {
            $this->workspace->members()->create([
                'user_id' => $person['user']->id,
                'role' => $person['role'],
                'status' => $person['status'],
                'joined_at' => $person['status'] === MemberStatus::Active
                    ? $this->shift($person['joined'])
                    : null,
                'invited_by' => $person['status'] === MemberStatus::Invited
                    ? $this->users[7]->id
                    : null,
            ]);
        }
    }

    private function seedLabels(): void
    {
        $labels = [
            'Design' => 'brand', 'Frontend' => 'info', 'Backend' => 'success',
            'Bug' => 'danger', 'Research' => 'warning', 'Content' => 'neutral',
            'Accessibility' => 'info', 'Infra' => 'neutral', 'Mobile' => 'brand',
        ];

        foreach ($labels as $name => $color) {
            $this->labels[$name] = $this->workspace->labels()->create([
                'name' => $name,
                'color' => $color,
            ]);
        }
    }

    /* ------------------------------------------------------------- projects */

    private function seedProjects(): void
    {
        $projects = [
            ['id' => 1, 'key' => 'WEB', 'name' => 'Website Redesign', 'status' => ProjectStatus::InProgress, 'priority' => Priority::High, 'color' => 'brand', 'lead' => 2, 'members' => [2, 4, 5, 6], 'start' => '2025-06-02', 'deadline' => '2025-09-12',
                'description' => 'Full marketing site rebuild with a new visual identity, CMS migration and a measurable lift in trial sign-ups.'],
            ['id' => 2, 'key' => 'MOB', 'name' => 'Mobile App Development', 'status' => ProjectStatus::InProgress, 'priority' => Priority::Critical, 'color' => 'info', 'lead' => 3, 'members' => [3, 4, 6, 1], 'start' => '2025-05-12', 'deadline' => '2025-11-28',
                'description' => 'Native iOS and Android clients with offline task sync, push notifications and biometric sign-in.'],
            ['id' => 3, 'key' => 'MKT', 'name' => 'Marketing Campaign', 'status' => ProjectStatus::InProgress, 'priority' => Priority::Medium, 'color' => 'warning', 'lead' => 8, 'members' => [8, 5, 2], 'start' => '2025-07-21', 'deadline' => '2025-10-03',
                'description' => 'Q4 product launch campaign across email, paid social and the partner network.'],
            ['id' => 4, 'key' => 'PRT', 'name' => 'Customer Portal', 'status' => ProjectStatus::InReview, 'priority' => Priority::High, 'color' => 'success', 'lead' => 1, 'members' => [3, 6, 1, 4, 5], 'start' => '2025-03-04', 'deadline' => '2025-09-05',
                'description' => 'Self-service portal for billing, seat management and support tickets, replacing the legacy admin.'],
            ['id' => 5, 'key' => 'API', 'name' => 'API v2 Migration', 'status' => ProjectStatus::Planning, 'priority' => Priority::Medium, 'color' => 'neutral', 'lead' => 7, 'members' => [3, 7, 1], 'start' => '2025-08-25', 'deadline' => '2026-01-16',
                'description' => 'Versioned REST API with Sanctum token auth, rate limiting and a published OpenAPI schema.'],
            ['id' => 6, 'key' => 'DS', 'name' => 'Design System 2.0', 'status' => ProjectStatus::OnHold, 'priority' => Priority::Low, 'color' => 'brand', 'lead' => 2, 'members' => [2, 5, 4], 'start' => '2025-04-14', 'deadline' => '2025-12-19',
                'description' => 'Token-driven component library shared by the marketing site, the web app and both mobile clients.'],
            ['id' => 7, 'key' => 'ONB', 'name' => 'Onboarding Revamp', 'status' => ProjectStatus::Completed, 'priority' => Priority::Medium, 'color' => 'success', 'lead' => 5, 'members' => [5, 4, 6], 'start' => '2025-01-08', 'deadline' => '2025-05-30',
                'description' => 'Rebuilt first-run experience with guided setup, sample data and in-product checklists.'],
            ['id' => 8, 'key' => 'SEC', 'name' => 'Security Hardening', 'status' => ProjectStatus::InProgress, 'priority' => Priority::Critical, 'color' => 'danger', 'lead' => 7, 'members' => [7, 3, 6], 'start' => '2025-07-01', 'deadline' => '2025-10-24',
                'description' => 'Two-factor rollout, session auditing and a third-party penetration test ahead of SOC 2.'],
        ];

        foreach ($projects as $data) {
            $project = $this->workspace->projects()->create([
                'key' => $data['key'],
                'name' => $data['name'],
                'description' => $data['description'],
                'status' => $data['status'],
                'priority' => $data['priority'],
                'color' => $data['color'],
                'start_date' => $this->shift($data['start']),
                'deadline' => $this->shift($data['deadline']),
                'lead_id' => $this->users[$data['lead']]->id,
                'created_by' => $this->users[$data['lead']]->id,
            ]);

            $project->createDefaultColumns();

            foreach ($data['members'] as $memberId) {
                $project->members()->attach($this->users[$memberId]->id, [
                    'role' => $memberId === $data['lead'] ? 'lead' : 'member',
                    'added_by' => $this->users[$data['lead']]->id,
                ]);
            }

            $this->projects[$data['id']] = $project;
        }

        // Jordan's pinned projects, in sidebar order
        $this->users[1]->favoriteProjects()->attach([
            $this->projects[1]->id => ['position' => 0],
            $this->projects[2]->id => ['position' => 1],
            $this->projects[4]->id => ['position' => 2],
        ]);
    }

    /* ---------------------------------------------------------------- tasks */

    private function seedTasks(): void
    {
        $tasks = [
            ['key' => 'WEB-118', 'project' => 1, 'title' => 'Design homepage hero section', 'column' => ColumnCategory::InProgress, 'priority' => Priority::High, 'assignee' => 5, 'reporter' => 2, 'start' => '2025-08-25', 'due' => '2025-09-02', 'labels' => ['Design'], 'attachments' => 3,
                'description' => "Explore three directions for the hero. Needs a headline, a product screenshot and a single primary call to action.\n\nShip the winning direction as a Figma component with light and dark variants.",
                'subtasks' => [['Collect reference sites and pull a moodboard', true], ['Draft three hero directions in Figma', true], ['Run a five-person preference test', false], ['Export assets at 1x and 2x', false]]],
            ['key' => 'API-204', 'project' => 5, 'title' => 'Implement authentication API', 'column' => ColumnCategory::InProgress, 'priority' => Priority::Critical, 'assignee' => 3, 'reporter' => 7, 'start' => '2025-08-26', 'due' => '2025-09-01', 'labels' => ['Backend', 'Infra'], 'attachments' => 1,
                'description' => 'Sanctum token issuance, refresh handling and rate limiting on the login route. Include contract tests for every error path.',
                'subtasks' => [['Token issuance endpoint', true], ['Refresh handling', true], ['Rate limiting', true], ['Contract tests', true], ['Document error responses', false], ['Security review', false]]],
            ['key' => 'WEB-121', 'project' => 1, 'title' => 'Create responsive navigation', 'column' => ColumnCategory::Review, 'priority' => Priority::Medium, 'assignee' => 4, 'reporter' => 2, 'start' => '2025-08-20', 'due' => '2025-09-04', 'labels' => ['Frontend', 'Accessibility'], 'attachments' => 0,
                'description' => 'Sticky top navigation with a mobile drawer, keyboard support and a visible focus ring at every breakpoint.',
                'subtasks' => [['Desktop layout', true], ['Mobile drawer', true], ['Keyboard and focus states', true]]],
            ['key' => 'API-198', 'project' => 5, 'title' => 'Review database schema', 'column' => ColumnCategory::Todo, 'priority' => Priority::Medium, 'assignee' => 7, 'reporter' => 3, 'start' => '2025-09-01', 'due' => '2025-09-08', 'labels' => ['Backend'], 'attachments' => 2,
                'description' => 'Walk through the proposed task/label pivot tables and confirm the indexes cover the board queries.',
                'subtasks' => [['Review pivot tables', false], ['Confirm board query indexes', false]]],
            ['key' => 'PRT-076', 'project' => 4, 'title' => 'Fix mobile checkout layout', 'column' => ColumnCategory::InProgress, 'priority' => Priority::Critical, 'assignee' => 4, 'reporter' => 6, 'start' => '2025-08-29', 'due' => '2025-08-31', 'labels' => ['Bug', 'Frontend'], 'attachments' => 4,
                'description' => 'The summary panel overlaps the sticky pay button below 380px. Reproduced on iPhone SE and Pixel 4a.',
                'subtasks' => [['Reproduce on device', true], ['Fix overlap', false], ['Regression test at 375px', false]]],
            ['key' => 'MOB-312', 'project' => 2, 'title' => 'Offline task sync conflict resolution', 'column' => ColumnCategory::Backlog, 'priority' => Priority::High, 'assignee' => 3, 'reporter' => 1, 'start' => '2025-09-08', 'due' => '2025-09-26', 'labels' => ['Mobile', 'Backend'], 'attachments' => 1,
                'description' => 'Define last-write-wins with a per-field merge for descriptions, and surface a conflict banner in the app.',
                'subtasks' => [['Define merge strategy', false], ['Conflict banner design', false], ['Implement client merge', false], ['Implement server merge', false], ['Test offline scenarios', false]]],
            ['key' => 'MKT-045', 'project' => 3, 'title' => 'Write launch announcement email', 'column' => ColumnCategory::Todo, 'priority' => Priority::Medium, 'assignee' => 8, 'reporter' => 2, 'start' => '2025-09-02', 'due' => '2025-09-10', 'labels' => ['Content'], 'attachments' => 0,
                'description' => 'Three-part sequence: teaser, launch day and a follow-up for trial users who did not activate.',
                'subtasks' => [['Teaser copy', false], ['Launch day copy', false], ['Follow-up copy', false]]],
            ['key' => 'DS-058', 'project' => 6, 'title' => 'Audit colour contrast across components', 'column' => ColumnCategory::Review, 'priority' => Priority::High, 'assignee' => 5, 'reporter' => 4, 'start' => '2025-08-18', 'due' => '2025-09-03', 'labels' => ['Accessibility', 'Design'], 'attachments' => 6,
                'description' => 'Every text/background pair must clear 4.5:1 in both themes. Record failures with a screenshot.',
                'subtasks' => [['Buttons', true], ['Badges', true], ['Inputs', true], ['Tables', true], ['Board cards', true], ['Modals', true], ['Toasts', true], ['Write up failures', false]]],
            ['key' => 'SEC-014', 'project' => 8, 'title' => 'Roll out two-factor authentication', 'column' => ColumnCategory::InProgress, 'priority' => Priority::Critical, 'assignee' => 7, 'reporter' => 7, 'start' => '2025-08-11', 'due' => '2025-09-15', 'labels' => ['Backend', 'Infra'], 'attachments' => 2,
                'description' => 'TOTP enrolment, recovery codes and an admin policy to require 2FA for the Admin role.',
                'subtasks' => [['TOTP enrolment', true], ['Recovery codes', true], ['Admin policy', true], ['Enrolment emails', false], ['Docs', false], ['Rollout plan', false], ['Monitor adoption', false]]],
            ['key' => 'WEB-103', 'project' => 1, 'title' => 'Migrate blog content to the new CMS', 'column' => ColumnCategory::Done, 'priority' => Priority::Low, 'assignee' => 8, 'reporter' => 2, 'start' => '2025-07-14', 'due' => '2025-08-22', 'labels' => ['Content'], 'attachments' => 1,
                'description' => '184 posts, including redirects for every legacy URL.',
                'subtasks' => [['Export posts', true], ['Import to CMS', true], ['Set up redirects', true], ['Spot-check 20 posts', true]]],
            ['key' => 'PRT-081', 'project' => 4, 'title' => 'Seat management empty state', 'column' => ColumnCategory::Done, 'priority' => Priority::Low, 'assignee' => 5, 'reporter' => 1, 'start' => '2025-08-04', 'due' => '2025-08-27', 'labels' => ['Design', 'Frontend'], 'attachments' => 2,
                'description' => 'Illustration, one line of copy and an Invite teammates action.',
                'subtasks' => [['Design', true], ['Build', true]]],
            ['key' => 'MOB-298', 'project' => 2, 'title' => 'Push notification permission flow', 'column' => ColumnCategory::Todo, 'priority' => Priority::High, 'assignee' => 4, 'reporter' => 3, 'start' => '2025-09-03', 'due' => '2025-09-19', 'labels' => ['Mobile', 'Frontend'], 'attachments' => 0,
                'description' => 'Soft-ask screen before the system prompt, with a deferred re-ask after day three.',
                'subtasks' => [['Soft-ask screen', false], ['System prompt trigger', false], ['Deferred re-ask', false], ['Analytics', false]]],
            ['key' => 'MKT-052', 'project' => 3, 'title' => 'Partner co-marketing landing page', 'column' => ColumnCategory::Backlog, 'priority' => Priority::Low, 'assignee' => 2, 'reporter' => 8, 'start' => '2025-09-15', 'due' => '2025-09-30', 'labels' => ['Design', 'Content'], 'attachments' => 0,
                'description' => 'Reusable template with logo, quote and a tracked sign-up form.',
                'subtasks' => [['Template design', false], ['Copy', false], ['Form tracking', false]]],
            ['key' => 'SEC-021', 'project' => 8, 'title' => 'Session audit log viewer', 'column' => ColumnCategory::Backlog, 'priority' => Priority::Medium, 'assignee' => 6, 'reporter' => 7, 'start' => '2025-09-10', 'due' => '2025-10-08', 'labels' => ['Frontend', 'Infra'], 'attachments' => 0,
                'description' => 'Filterable table of sign-in events with device, IP and location.',
                'subtasks' => [['Table layout', false], ['Filters', false], ['Device parsing', false], ['Geo lookup', false], ['Pagination', false]]],
            ['key' => 'DS-061', 'project' => 6, 'title' => 'Document motion and easing tokens', 'column' => ColumnCategory::Backlog, 'priority' => Priority::Low, 'assignee' => 4, 'reporter' => 2, 'start' => '2025-09-22', 'due' => '2025-10-17', 'labels' => ['Design'], 'attachments' => 1,
                'description' => 'Durations, curves and the reduced-motion policy, with runnable examples.',
                'subtasks' => [['Document durations', false], ['Document curves', false], ['Reduced-motion policy', false]]],
            ['key' => 'WEB-127', 'project' => 1, 'title' => 'Pricing page comparison table', 'column' => ColumnCategory::InProgress, 'priority' => Priority::Medium, 'assignee' => 4, 'reporter' => 5, 'start' => '2025-08-27', 'due' => '2025-09-06', 'labels' => ['Frontend', 'Design'], 'attachments' => 1,
                'description' => 'Four plans, sticky header row and a mobile card layout below 768px.',
                'subtasks' => [['Desktop table', true], ['Sticky header', false], ['Mobile cards', false], ['Plan copy', false]]],
            ['key' => 'PRT-084', 'project' => 4, 'title' => 'Invoice PDF export', 'column' => ColumnCategory::Review, 'priority' => Priority::Medium, 'assignee' => 3, 'reporter' => 1, 'start' => '2025-08-22', 'due' => '2025-09-05', 'labels' => ['Backend'], 'attachments' => 2,
                'description' => 'Server-rendered PDF with the workspace logo and VAT details.',
                'subtasks' => [['PDF template', true], ['VAT details', true], ['Queue the render', false]]],
            ['key' => 'MOB-305', 'project' => 2, 'title' => 'Biometric sign-in for iOS', 'column' => ColumnCategory::Done, 'priority' => Priority::High, 'assignee' => 6, 'reporter' => 3, 'start' => '2025-07-29', 'due' => '2025-08-25', 'labels' => ['Mobile'], 'attachments' => 0,
                'description' => 'Face ID unlock with a passcode fallback and a settings toggle.',
                'subtasks' => [['Face ID unlock', true], ['Passcode fallback', true], ['Settings toggle', true], ['QA on device', true]]],
        ];

        $positions = [];

        foreach ($tasks as $data) {
            $project = $this->projects[$data['project']];
            $column = $project->boardColumns->firstWhere('category', $data['column'])
                ?? BoardColumn::where('project_id', $project->id)
                    ->where('category', $data['column'])
                    ->first();

            $number = (int) explode('-', $data['key'])[1];
            $positionKey = $column->id;
            $positions[$positionKey] = ($positions[$positionKey] ?? 0) + Task::POSITION_GAP;

            $task = Task::create([
                'workspace_id' => $this->workspace->id,
                'project_id' => $project->id,
                'board_column_id' => $column->id,
                'number' => $number,
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => $data['priority'],
                'assignee_id' => $this->users[$data['assignee']]->id,
                'reporter_id' => $this->users[$data['reporter']]->id,
                'created_by' => $this->users[$data['reporter']]->id,
                'start_date' => $this->shift($data['start']),
                'due_date' => $this->shift($data['due']),
                'position' => (string) $positions[$positionKey],
                'completed_at' => $data['column'] === ColumnCategory::Done
                    ? $this->shift($data['due'])
                    : null,
            ]);

            // Keep the project's key allocator ahead of the seeded numbers
            if ($number >= $project->next_task_number) {
                $project->forceFill(['next_task_number' => $number + 1])->save();
            }

            foreach ($data['labels'] as $labelName) {
                $task->labels()->attach($this->labels[$labelName]->id);
            }

            foreach ($data['subtasks'] as $index => [$title, $done]) {
                Subtask::create([
                    'task_id' => $task->id,
                    'title' => $title,
                    'is_completed' => $done,
                    'completed_at' => $done ? $this->shift($data['start']) : null,
                    'completed_by' => $done ? $this->users[$data['assignee']]->id : null,
                    'position' => $index,
                ]);
            }

            $task->forceFill([
                'subtasks_total' => count($data['subtasks']),
                'subtasks_completed' => count(array_filter($data['subtasks'], fn ($s) => $s[1])),
            ])->save();

            // Reporter and assignee watch their own work
            $task->watchers()->syncWithoutDetaching([
                $this->users[$data['assignee']]->id,
                $this->users[$data['reporter']]->id,
            ]);
        }

        foreach ($this->projects as $project) {
            $project->recountTasks();
        }
    }

    /* ------------------------------------------------- background workload */

    /**
     * The prototype's project cards quote totals the eighteen detailed tasks
     * can't account for — Website Redesign shows 42 tasks, 29 done, 68%.
     *
     * Rather than storing those figures as numbers, the remaining tasks are
     * seeded as real rows so every count, progress bar and workload meter is
     * derived from the table it claims to describe.
     *
     * @var array<int, array{0: int, 1: int}> [total, completed] per project
     */
    private const PROJECT_TOTALS = [
        1 => [42, 29], 2 => [64, 26], 3 => [28, 15], 4 => [37, 31],
        5 => [19, 2], 6 => [24, 8], 7 => [31, 31], 8 => [22, 10],
    ];

    /** @var array<string, string[]> filler titles by project key prefix */
    private const FILLER_TITLES = [
        'WEB' => ['Rewrite the {n} page copy', 'Compress hero imagery for {n}', 'Add structured data to {n} templates', 'Fix heading hierarchy on {n}', 'Set up redirects for the {n} section', 'Review analytics events on {n}', 'Build the {n} component', 'Cross-browser check: {n}'],
        'MOB' => ['Handle offline state in {n}', 'Add pull-to-refresh to {n}', 'Instrument {n} analytics', 'Fix keyboard avoidance on {n}', 'Cache {n} responses locally', 'Add haptics to {n}', 'Localise strings for {n}', 'Profile render cost of {n}'],
        'MKT' => ['Draft social copy for {n}', 'Brief the {n} creative', 'Schedule the {n} send', 'Build the {n} landing page', 'Set up tracking for {n}', 'Review {n} performance', 'Source imagery for {n}', 'Approve {n} with legal'],
        'PRT' => ['Add validation to {n}', 'Paginate the {n} table', 'Empty state for {n}', 'Permission checks on {n}', 'Audit log entries for {n}', 'Export {n} to CSV', 'Rate limit the {n} endpoint', 'Error handling for {n}'],
        'API' => ['Document the {n} endpoint', 'Version the {n} response', 'Add contract tests for {n}', 'Deprecate the legacy {n} route', 'Rate limit {n}', 'Add pagination to {n}', 'Benchmark {n} queries', 'Add an index for {n}'],
        'DS' => ['Document the {n} component', 'Add dark variants to {n}', 'Write tests for {n}', 'Publish {n} tokens', 'Audit {n} spacing', 'Add {n} to the storybook', 'Deprecate the old {n}', 'Accessibility pass on {n}'],
        'ONB' => ['Write the {n} step copy', 'Build the {n} checklist item', 'Track completion of {n}', 'Design the {n} illustration', 'Add sample data for {n}', 'Test {n} on a fresh account', 'Handle skip on {n}', 'Email follow-up for {n}'],
        'SEC' => ['Threat model {n}', 'Add audit logging to {n}', 'Rotate credentials for {n}', 'Penetration test {n}', 'Enforce policy on {n}', 'Alerting for {n}', 'Review third-party access to {n}', 'Document the {n} runbook'],
    ];

    /** @var string[] nouns dropped into the filler titles */
    private const FILLER_NOUNS = [
        'pricing', 'checkout', 'settings', 'search', 'billing', 'the dashboard',
        'notifications', 'the profile screen', 'onboarding', 'the task list',
        'the board', 'file uploads', 'the activity feed', 'invitations',
        'the team page', 'session handling', 'the comment thread', 'reporting',
    ];

    private function topUpProjects(): void
    {
        // Deterministic filler so re-seeding produces the same database
        mt_srand(20250831);

        foreach (self::PROJECT_TOTALS as $projectId => [$total, $completed]) {
            $project = $this->projects[$projectId];

            $existing = $project->tasks()->count();
            $existingDone = $project->tasks()->whereNotNull('completed_at')->count();

            $toCreate = max(0, $total - $existing);
            $doneToCreate = max(0, $completed - $existingDone);

            $columns = $project->boardColumns()->get()->keyBy(fn ($c) => $c->category->value);
            $memberIds = $project->members()->pluck('users.id')->all();
            $labelIds = collect($this->labels)->pluck('id')->all();
            $titles = self::FILLER_TITLES[$project->key];

            $positions = [];

            for ($i = 0; $i < $toCreate; $i++) {
                $isDone = $i < $doneToCreate;

                // Spread the unfinished work across the four open columns
                $category = $isDone ? 'done' : ['backlog', 'todo', 'in_progress', 'review'][$i % 4];
                $column = $columns[$category];

                $title = str_replace(
                    '{n}',
                    self::FILLER_NOUNS[mt_rand(0, count(self::FILLER_NOUNS) - 1)],
                    $titles[$i % count($titles)],
                );

                $assignee = $memberIds[mt_rand(0, count($memberIds) - 1)];
                $reporter = $project->lead_id;

                // Finished work sits in the past; open work spreads forward
                $due = $isDone
                    ? $this->today->copy()->subDays(mt_rand(3, 90))
                    : $this->today->copy()->addDays(mt_rand(-2, 60));

                $positions[$column->id] = ($positions[$column->id] ?? 100_000) + Task::POSITION_GAP;

                $task = Task::create([
                    'workspace_id' => $this->workspace->id,
                    'project_id' => $project->id,
                    'board_column_id' => $column->id,
                    'number' => $project->allocateTaskNumber(),
                    'title' => $title,
                    'priority' => Priority::cases()[mt_rand(0, 3)],
                    'assignee_id' => $assignee,
                    'reporter_id' => $reporter,
                    'created_by' => $reporter,
                    'start_date' => $due->copy()->subDays(mt_rand(2, 14)),
                    'due_date' => $due,
                    'position' => (string) $positions[$column->id],
                    'completed_at' => $isDone ? $due : null,
                ]);

                if (mt_rand(0, 2) > 0) {
                    $task->labels()->attach($labelIds[mt_rand(0, count($labelIds) - 1)]);
                }
            }

            $project->recountTasks();
        }
    }

    /* ------------------------------------------------------------- comments */

    private function seedComments(): void
    {
        $hero = Task::where('title', 'Design homepage hero section')->firstOrFail();

        $comments = [
            [2, '2025-08-28 09:41:00', "Direction B tested best in the moderated sessions — people read the headline before the screenshot. Let's take that one forward and drop the animated gradient.", 3],
            [5, '2025-08-28 11:07:00', 'Agreed. I\'ve rebuilt it as a component with light and dark variants. One open question: do we keep the secondary "Book a demo" button above the fold?', 1],
            [1, '2025-08-29 08:15:00', 'Keep it, but as a text link rather than a filled button. Two competing buttons hurt the trial conversion last quarter.', 2],
            [4, '2025-08-29 14:52:00', 'Implementation note: the screenshot needs a 2x asset or it looks soft on retina. @Emily can you export both sizes?', 0],
        ];

        foreach ($comments as [$authorId, $at, $body, $reactions]) {
            $comment = Comment::create([
                'task_id' => $hero->id,
                'user_id' => $this->users[$authorId]->id,
                'body' => $body,
            ]);

            $comment->forceFill(['created_at' => $this->shift($at), 'updated_at' => $this->shift($at)])->save();

            // A thumbs-up from the first N other people
            $reactors = array_slice(array_diff([1, 2, 3, 4, 5], [$authorId]), 0, $reactions);
            foreach ($reactors as $reactorId) {
                $comment->reactions()->create([
                    'user_id' => $this->users[$reactorId]->id,
                    'emoji' => '👍',
                ]);
            }
        }

        $hero->forceFill(['comments_count' => $hero->allComments()->count()])->save();
    }

    /* --------------------------------------------------------- notifications */

    /**
     * The notification centre the prototype showed, addressed to Jordan.
     * Stored in Laravel's standard notifications shape plus the `category`
     * column the centre's tabs filter on.
     */
    private function seedNotifications(): void
    {
        $me = $this->users[1];

        $items = [
            ['mention', 4, 'Alex Johnson mentioned you in a comment', '@Jordan can you confirm the breakpoint we agreed on for the summary panel?', 180, false],
            ['task', 7, 'Priya Nair assigned you a task', 'Review database schema · API v2 Migration', 120, false],
            ['project', null, 'Customer Portal deadline is approaching', '5 open tasks remain and the deadline is in 5 days.', 360, false],
            ['task', 6, 'Daniel Smith moved a task to Review', 'Create responsive navigation · Website Redesign', 1_500, true],
            ['mention', 2, 'Sarah Wilson mentioned you in Design System 2.0', '@Jordan the contrast audit is ready for a second pair of eyes.', 1_800, true],
            ['system', null, 'New sign-in from Chrome on macOS', 'San Francisco, CA. If this was not you, review your sessions.', 2_880, true],
            ['project', 8, 'Tom Becker added you to Marketing Campaign', 'You now have Manager access to this project.', 4_320, true],
            ['system', null, 'Your weekly summary is ready', 'You completed 9 tasks last week, 2 more than the week before.', 5_760, true],
        ];

        foreach ($items as [$category, $actorId, $title, $body, $minutesAgo, $read]) {
            $at = now()->subMinutes($minutesAgo);
            $actor = $actorId === null ? null : $this->users[$actorId];

            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\WorkspaceActivity',
                'notifiable_type' => $me->getMorphClass(),
                'notifiable_id' => $me->id,
                'category' => $category,
                'actor_id' => $actor?->id,
                'data' => json_encode([
                    'title' => $title,
                    'body' => $body,
                    'actor' => $actor?->name,
                    'href' => null,
                ]),
                'read_at' => $read ? $at : null,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    /* ------------------------------------------------------------- activity */

    private function seedActivity(): void
    {
        $feed = [
            [2, 'task.created', 1, 'Design homepage hero section', 12],
            [3, 'task.completed', 2, 'Biometric sign-in for iOS', 48],
            [7, 'task.assigned', 5, 'Review database schema', 120],
            [4, 'comment.created', 4, 'Fix mobile checkout layout', 180],
            [5, 'attachment.uploaded', 6, 'Audit colour contrast across components', 300],
            [6, 'task.moved', 1, 'Create responsive navigation', 1080],
            [8, 'project.created', 3, 'Marketing Campaign', 1380],
        ];

        foreach ($feed as [$actorId, $verb, $projectId, $target, $minutesAgo]) {
            $task = Task::where('title', $target)->first();

            $activity = Activity::log(
                workspaceId: $this->workspace->id,
                verb: $verb,
                actorId: $this->users[$actorId]->id,
                projectId: $this->projects[$projectId]->id,
                taskId: $task?->id,
                subject: $task ?? $this->projects[$projectId],
                properties: $verb === 'task.moved'
                    ? ['from' => 'In Progress', 'to' => 'Review']
                    : [],
            );

            $activity->forceFill([
                'created_at' => now()->subMinutes($minutesAgo),
            ])->save();
        }
    }
}
