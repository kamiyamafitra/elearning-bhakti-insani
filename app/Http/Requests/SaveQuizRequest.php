<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveQuizRequest extends FormRequest
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
            'teaching' => ['required', 'string', 'regex:/^\d+-\d+$/'],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'shuffles' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'teaching' => 'mata pelajaran dan kelas',
            'title' => 'judul',
            'instructions' => 'petunjuk',
            'opens_at' => 'waktu dibuka',
            'closes_at' => 'waktu ditutup',
            'duration_minutes' => 'durasi',
        ];
    }
}
