<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiscussionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'room' => ['required', 'regex:/^\d+:\d+$/'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:'.StoreQuizQuestionsRequest::MAX_IMAGE_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'room.required' => 'Pilih kelas dan mata pelajaran.',
            'room.regex' => 'Pilih kelas dan mata pelajaran.',
            'title.required' => 'Judul wajib diisi.',
            'title.max' => 'Judul maksimal 255 karakter.',
            'body.required' => 'Isi diskusi wajib diisi.',
            'body.max' => 'Isi diskusi maksimal 5000 karakter.',
            'image.file' => 'Gambar gagal diunggah. Coba lagi.',
            'image.mimes' => 'Gambar harus berformat PNG, JPG, atau WEBP.',
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
        ];
    }
}
