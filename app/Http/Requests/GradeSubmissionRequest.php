<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use Illuminate\Foundation\Http\FormRequest;

class GradeSubmissionRequest extends FormRequest
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
        $assignment = $this->route('assignment');
        $maxScore = $assignment instanceof Assignment ? $assignment->max_score : 100;

        return [
            'score' => ['required', 'integer', 'min:0', 'max:'.$maxScore],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
