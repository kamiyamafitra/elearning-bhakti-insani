<?php

namespace App\Http\Requests;

use App\Models\QuizQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreQuizQuestionsRequest extends FormRequest
{
    /**
     * PHP accepts 20 uploaded files per request by default, and each question can carry one image.
     */
    public const MAX_QUESTIONS = 20;

    public const MAX_IMAGE_KILOBYTES = 2048;

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
            'questions' => ['required', 'array', 'max:'.self::MAX_QUESTIONS],
            'questions.*.question' => ['required', 'string', 'max:5000'],
            'questions.*.image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:'.self::MAX_IMAGE_KILOBYTES],
            'questions.*.options' => ['required', 'array', 'max:'.QuizQuestion::MAX_OPTIONS],
            'questions.*.options.*' => ['nullable', 'string', 'max:1000'],
            'questions.*.correct_option' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'questions.required' => 'Tambahkan minimal satu soal.',
            'questions.max' => 'Maksimal '.self::MAX_QUESTIONS.' soal sekali simpan.',
            'questions.*.question.required' => 'Pertanyaan wajib diisi.',
            'questions.*.correct_option.required' => 'Pilih satu jawaban yang benar.',
            'questions.*.image.mimes' => 'Gambar harus berformat PNG, JPG, atau WEBP.',
            'questions.*.image.max' => 'Ukuran gambar maksimal 2 MB.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('questions') as $key => $question) {
                $options = array_filter((array) ($question['options'] ?? []), 'filled');

                if (count($options) < QuizQuestion::MIN_OPTIONS) {
                    $validator->errors()->add("questions.{$key}.options", 'Isi minimal '.QuizQuestion::MIN_OPTIONS.' pilihan jawaban.');
                }

                if (filled($question['correct_option'] ?? null) && ! array_key_exists((int) $question['correct_option'], $options)) {
                    $validator->errors()->add("questions.{$key}.correct_option", 'Jawaban benar harus pilihan yang sudah diisi.');
                }
            }
        }];
    }
}
