<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiscussionReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('discussion_replies', 'id')->where('discussion_id', $this->route('discussion')->id)],
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
            'parent_id.exists' => 'Balasan yang ingin ditanggapi sudah tidak tersedia.',
            'body.required' => 'Balasan wajib diisi.',
            'body.max' => 'Balasan maksimal 5000 karakter.',
            'image.file' => 'Gambar gagal diunggah. Coba lagi.',
            'image.mimes' => 'Gambar harus berformat PNG, JPG, atau WEBP.',
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
        ];
    }
}
