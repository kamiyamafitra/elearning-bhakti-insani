<?php

namespace App\Http\Requests;

use App\Models\QuizQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveQuizQuestionRequest extends FormRequest
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
            'question' => ['required', 'string', 'max:5000'],
            'image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'options' => ['required', 'array', 'max:'.QuizQuestion::MAX_OPTIONS],
            'options.*' => ['nullable', 'string', 'max:1000'],
            'correct_option' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'Pertanyaan wajib diisi.',
            'correct_option.required' => 'Pilih satu jawaban yang benar.',
            'image.mimes' => 'Gambar harus berformat PNG, JPG, atau WEBP.',
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['question' => 'pertanyaan'];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $options = array_filter((array) $this->input('options'), 'filled');

            if (count($options) < QuizQuestion::MIN_OPTIONS) {
                $validator->errors()->add('options', 'Isi minimal '.QuizQuestion::MIN_OPTIONS.' pilihan jawaban.');
            }

            if ($this->filled('correct_option') && ! array_key_exists((int) $this->input('correct_option'), $options)) {
                $validator->errors()->add('correct_option', 'Jawaban benar harus pilihan yang sudah diisi.');
            }
        }];
    }
}
