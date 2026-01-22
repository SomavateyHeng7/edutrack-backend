<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGraduationSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * 
     * Authorization is handled by the graduation.session middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_identifier' => 'required|string|max:255',
            'curriculum_id' => 'required|exists:curricula,id',
            'courses' => 'required|array|min:1',
            'courses.*.code' => 'required|string|max:50',
            'courses.*.name' => 'nullable|string|max:255',
            'courses.*.credits' => 'required|numeric|min:0|max:12',
            // Grade is OPTIONAL - planned/in_progress courses may not have grades
            'courses.*.grade' => 'nullable|string|max:10',
            'courses.*.status' => 'required|in:completed,in_progress,planned,failed,withdrawn',
            'courses.*.semester' => 'nullable|string|max:50',
            'courses.*.category' => 'nullable|string|max:100',
            'metadata' => 'nullable|array',
            'metadata.parsed_at' => 'nullable|date',
            'metadata.file_name' => 'nullable|string|max:255',
            'metadata.total_courses' => 'nullable|integer',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'student_identifier.required' => 'Student identifier is required.',
            'curriculum_id.required' => 'Curriculum selection is required.',
            'curriculum_id.exists' => 'Selected curriculum does not exist.',
            'courses.required' => 'At least one course must be submitted.',
            'courses.min' => 'At least one course must be submitted.',
            'courses.*.code.required' => 'Course code is required for all courses.',
            'courses.*.credits.required' => 'Credits are required for all courses.',
            'courses.*.status.required' => 'Status is required for all courses.',
            'courses.*.status.in' => 'Invalid course status. Must be: completed, in_progress, planned, failed, or withdrawn.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize course statuses
        if ($this->has('courses')) {
            $courses = collect($this->courses)->map(function ($course) {
                if (isset($course['status'])) {
                    $course['status'] = $this->normalizeStatus($course['status']);
                }
                return $course;
            })->toArray();
            
            $this->merge(['courses' => $courses]);
        }
    }

    /**
     * Normalize status values to standard format.
     */
    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        
        $mappings = [
            'complete' => 'completed',
            'pass' => 'completed',
            'passed' => 'completed',
            'in progress' => 'in_progress',
            'inprogress' => 'in_progress',
            'taking' => 'in_progress',
            'current' => 'in_progress',
            'plan' => 'planned',
            'planning' => 'planned',
            'future' => 'planned',
            'fail' => 'failed',
            'f' => 'failed',
            'withdraw' => 'withdrawn',
            'dropped' => 'withdrawn',
            'w' => 'withdrawn',
        ];
        
        return $mappings[$normalized] ?? $normalized;
    }
}
