<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string', 'max:20000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip,odt,ods,odp', 'max:20480'],
            'due_at' => ['required', 'date'],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'],
            'status' => ['required', Rule::in([Assignment::STATUS_DRAFT, Assignment::STATUS_PUBLISHED])],
            'allows_text_response' => ['nullable', 'boolean'],
            'allows_resubmission' => ['nullable', 'boolean'],
        ];
    }
}
