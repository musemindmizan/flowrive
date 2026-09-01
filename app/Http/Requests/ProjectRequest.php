<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by create and update. The project key is unique per workspace, not
 * globally — two workspaces may both have a "WEB".
 */
class ProjectRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspaceId = $this->user()->currentWorkspace()?->id;
        // route() is typed object|string, so narrow before reading the key
        $project = $this->route('project');
        $projectId = $project instanceof Project ? $project->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'key' => [
                'required', 'string', 'max:6', 'regex:/^[A-Z][A-Z0-9]*$/',
                Rule::unique('projects', 'key')
                    ->where('workspace_id', $workspaceId)
                    ->ignore($projectId)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'visibility' => ['required', 'in:members,workspace'],
            'color' => ['required', 'string', 'max:20'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'lead_id' => ['nullable', 'integer', 'exists:users,id'],
            'members' => ['array'],
            'members.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => 'The key must start with a letter and use capitals and digits only, e.g. WEB or API2.',
            'key.unique' => 'Another project in this workspace already uses that key.',
            'deadline.after_or_equal' => 'The deadline cannot fall before the start date.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('key')) {
            $this->merge(['key' => strtoupper((string) $this->input('key'))]);
        }
    }
}
