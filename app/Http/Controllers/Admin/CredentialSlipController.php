<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\WritesSpreadsheetRows;
use App\Http\Controllers\Controller;
use App\Support\CredentialSlip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CredentialSlipController extends Controller
{
    use WritesSpreadsheetRows;

    /**
     * Download the temporary passwords created by an import or class reset. Works once; the slip is deleted afterwards.
     */
    public function download(string $slip): BinaryFileResponse|RedirectResponse
    {
        $data = CredentialSlip::pull($slip);

        if ($data === null) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Slip password sudah pernah diunduh atau sudah kedaluwarsa. Reset ulang password bila masih diperlukan.');
        }

        $header = ['No', 'Nama', $data['identifier_label'], 'Kelas', 'Password Sementara', 'Tanda Terima'];

        $path = tempnam(sys_get_temp_dir(), 'credential-slip');
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Slip Password');
        $this->layoutSheet($writer->getCurrentSheet(), [6, 32, 20, 22, 20, 18], 3);
        $this->addTitleRow($writer, "Slip Password Sementara - {$data['title']}", count($header));
        $this->addNoteRow($writer, "Masuk dengan {$data['identifier_label']} dan password sementara, lalu wajib membuat password baru. Simpan dengan aman dan jangan dibagikan.", count($header));
        $writer->addRow($this->headerRow($header));

        foreach ($data['rows'] as $index => $row) {
            $writer->addRow($this->dataRow([
                $index + 1,
                $row['name'],
                $row['identifier'],
                $row['group'],
                $row['password'],
                '',
            ], $index));
        }

        $writer->close();

        return response()->download($path, 'slip-password-'.Str::slug($data['title']).'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend();
    }
}
