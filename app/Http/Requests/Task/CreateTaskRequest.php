<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class CreateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'priorityId' => 'required|integer|min:1',
            'assigneeIds' => 'nullable|string',
            'storyPoints' => 'required|integer|min:1',
            'startDate' => 'required|date',
            'description' => 'nullable|string',
            'parentTaskId' => 'nullable|integer|min:1',
            'dueDate' => 'nullable|date',
            'redirect_to' => 'nullable|string|max:64',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $fibonacci = [1, 2, 3, 5, 8, 13, 21];
            $points = (int) $this->input('storyPoints', 0);
            if (! in_array($points, $fibonacci, true)) {
                $validator->errors()->add('storyPoints', 'Story points must be a Fibonacci number (1, 2, 3, 5, 8, 13, 21).');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'priorityId.required' => 'Please select a priority.',
            'priorityId.min' => 'Please select a valid priority.',
            'storyPoints.required' => 'Story points are required.',
        ];
    }
}
