<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\WritesImagesToPdf;
use App\Http\Requests\ConvertToPdfRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PdfConverterController extends Controller
{
    use WritesImagesToPdf;

    public function index(Request $request): View
    {
        $role = $request->user()->role;

        return view('pdf-converter.index', [
            'layout' => "{$role}.layouts.dashboard",
            'storeRoute' => route("{$role}.pdf-converter.store"),
            'maxFiles' => ConvertToPdfRequest::MAX_FILES,
            'maxFileKilobytes' => ConvertToPdfRequest::MAX_FILE_KILOBYTES,
            'imageExtensions' => ConvertToPdfRequest::IMAGE_EXTENSIONS,
        ]);
    }

    public function store(ConvertToPdfRequest $request): BinaryFileResponse|RedirectResponse
    {
        /** @var list<UploadedFile> $files */
        $files = $request->file('files');
        $outputPath = tempnam(sys_get_temp_dir(), 'pdf-conversion');

        try {
            $this->writeImagesToPdf(array_map(fn (UploadedFile $file): string => $file->getPathname(), $files), $outputPath);
        } catch (RuntimeException $exception) {
            report($exception);
            File::delete($outputPath);

            return back()->with('error', 'Gambar tidak dapat dikonversi. Pastikan file tidak rusak, lalu coba lagi.');
        }

        $fileName = (Str::slug(pathinfo($files[0]->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'hasil-konversi').'.pdf';

        return response()->download($outputPath, $fileName, ['Content-Type' => 'application/pdf'])->deleteFileAfterSend();
    }
}
