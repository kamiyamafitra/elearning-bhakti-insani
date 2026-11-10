<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAssignmentRequest extends FormRequest
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
            'remove_attachment' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Once students have handed work in, the assignment can't move to another class (their submissions would
     * follow it into a class they don't belong to), and the maximum score can't drop below a score already given
     * (that would push grades over 100%).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $assignment = $this->route('assignment');

            if (! $assignment instanceof Assignment || $validator->errors()->isNotEmpty()) {
                return;
            }

            $submissions = $assignment->submissions();

            if ((int) $this->input('class_id') !== (int) $assignment->class_id && $submissions->exists()) {
                $validator->errors()->add('class_id', 'Kelas tujuan tidak dapat diubah karena siswa sudah mengumpulkan tugas ini.');
            }

            $highestScore = (int) $assignment->submissions()->max('score');

            if ((int) $this->input('max_score') < $highestScore) {
                $validator->errors()->add('max_score', "Skor maksimal tidak boleh lebih kecil dari nilai tertinggi yang sudah diberikan ({$highestScore}).");
            }
        }];
    }
}
