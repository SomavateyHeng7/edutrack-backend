<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGraduationPortalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        
        // Only chairpersons and admins can update portals
        return $user && in_array(strtoupper($user->role), ['CHAIRPERSON', 'ADMIN']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'batch' => 'nullable|string|max:100',
            'curriculum' => 'nullable|string|max:255',
            'curriculum_id' => 'nullable|exists:curricula,id',
            'deadline' => 'sometimes|required|date',
            'status' => 'nullable|in:active,closed',
            'accepted_formats' => 'nullable|array',
            'accepted_formats.*' => 'string|in:.xlsx,.xls,.csv',
            'max_file_size_mb' => 'nullable|integer|min:1|max:20',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Portal name is required.',
            'deadline.required' => 'Deadline is required.',
            'curriculum_id.exists' => 'Selected curriculum does not exist.',
            'max_file_size_mb.max' => 'Maximum file size cannot exceed 20 MB.',
        ];
    }
}
