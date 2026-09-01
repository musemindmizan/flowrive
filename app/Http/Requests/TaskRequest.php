<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'board_column_id' => ['required', 'integer', 'exists:board_columns,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:20000'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimate_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'labels' => ['array'],
            'labels.*' => ['integer', 'exists:labels,id'],
            'subtasks' => ['array'],
            'subtasks.*' => ['string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_date.after_or_equal' => 'The due date cannot fall before the start date.',
        ];
    }
}
