<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class ConvertToPdfRequest extends FormRequest
{
    public const int MAX_FILES = 10;

    public const int MAX_FILE_KILOBYTES = 10240;

    public const int MAX_IMAGE_PIXELS = 50_000_000;

    public const array IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

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
            'files' => ['required', 'array', 'max:'.self::MAX_FILES],
            'files.*' => [
                'file',
                'max:'.self::MAX_FILE_KILOBYTES,
                'extensions:'.implode(',', self::IMAGE_EXTENSIONS),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Pilih gambar yang akan dikonversi.',
            'files.max' => 'Maksimal '.self::MAX_FILES.' gambar sekali konversi.',
            'files.*.file' => 'File gagal diunggah. Coba lagi.',
            'files.*.max' => 'Ukuran setiap gambar maksimal '.(self::MAX_FILE_KILOBYTES / 1024).' MB.',
            'files.*.extensions' => 'Format gambar harus JPG, PNG, atau WEBP.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($this->file('files') as $file) {
                if ($problem = $this->contentProblem($file)) {
                    $validator->errors()->add('files', $problem);
                }
            }
        }];
    }

    /**
     * The extension alone proves nothing, so confirm the file really is an image of a supported size.
     */
    private function contentProblem(UploadedFile $file): ?string
    {
        $name = $file->getClientOriginalName();
        $imageSize = @getimagesize($file->getPathname());

        if ($imageSize === false || ! in_array($imageSize[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return "File \"{$name}\" bukan gambar yang valid.";
        }

        return $imageSize[0] * $imageSize[1] > self::MAX_IMAGE_PIXELS
            ? "Resolusi gambar \"{$name}\" terlalu besar."
            : null;
    }
}
