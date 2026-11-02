<?php

namespace App\Http\Controllers\Concerns;

use GdImage;
use RuntimeException;

trait WritesImagesToPdf
{
    private const float A4_SHORT_EDGE_POINTS = 595.28;

    private const float A4_LONG_EDGE_POINTS = 841.89;

    private const float PAGE_MARGIN_POINTS = 28.35;

    private const int MAX_IMAGE_EDGE_PIXELS = 3508;

    private const int JPEG_QUALITY = 88;

    /**
     * Write one A4 page per image, turned to suit the image and with the image centered inside the margins.
     *
     * Objects 1 and 2 are the catalog and page tree; every page then adds a page, a content stream, and an image object.
     *
     * @param  list<string>  $imagePaths
     */
    protected function writeImagesToPdf(array $imagePaths, string $outputPath): void
    {
        $pdf = fopen($outputPath, 'wb');
        $offsets = [];

        $writeObject = function (int $number, string $body) use ($pdf, &$offsets): void {
            $offsets[$number] = ftell($pdf);
            fwrite($pdf, "{$number} 0 obj\n{$body}\nendobj\n");
        };

        $pageNumbers = array_map(fn (int $index): int => 3 + $index * 3, array_keys($imagePaths));
        $pageReferences = implode(' ', array_map(fn (int $number): string => "{$number} 0 R", $pageNumbers));

        fwrite($pdf, "%PDF-1.4\n");
        $writeObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
        $writeObject(2, '<< /Type /Pages /Kids ['.$pageReferences.'] /Count '.count($imagePaths).' >>');

        foreach ($imagePaths as $index => $imagePath) {
            $image = $this->loadImageForPdf($imagePath);
            $widthPixels = imagesx($image);
            $heightPixels = imagesy($image);

            ob_start();
            imagejpeg($image, null, self::JPEG_QUALITY);
            $jpeg = ob_get_clean();
            unset($image);

            [$pageWidth, $pageHeight] = $widthPixels > $heightPixels
                ? [self::A4_LONG_EDGE_POINTS, self::A4_SHORT_EDGE_POINTS]
                : [self::A4_SHORT_EDGE_POINTS, self::A4_LONG_EDGE_POINTS];

            $scale = min(
                ($pageWidth - self::PAGE_MARGIN_POINTS * 2) / $widthPixels,
                ($pageHeight - self::PAGE_MARGIN_POINTS * 2) / $heightPixels,
            );
            $drawnWidth = $widthPixels * $scale;
            $drawnHeight = $heightPixels * $scale;

            $content = sprintf(
                'q %.2F 0 0 %.2F %.2F %.2F cm /Image Do Q',
                $drawnWidth,
                $drawnHeight,
                ($pageWidth - $drawnWidth) / 2,
                ($pageHeight - $drawnHeight) / 2,
            );

            $pageNumber = $pageNumbers[$index];
            $contentNumber = $pageNumber + 1;
            $imageNumber = $pageNumber + 2;

            $writeObject($pageNumber, sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Contents %d 0 R /Resources << /XObject << /Image %d 0 R >> >> >>',
                $pageWidth,
                $pageHeight,
                $contentNumber,
                $imageNumber,
            ));
            $writeObject($contentNumber, '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream");
            $writeObject($imageNumber, sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $widthPixels,
                $heightPixels,
                strlen($jpeg),
                $jpeg,
            ));
        }

        $crossReferenceOffset = ftell($pdf);
        $objectCount = count($offsets) + 1;

        fwrite($pdf, "xref\n0 {$objectCount}\n0000000000 65535 f \n");

        ksort($offsets);

        foreach ($offsets as $offset) {
            fwrite($pdf, sprintf("%010d 00000 n \n", $offset));
        }

        fwrite($pdf, "trailer\n<< /Size {$objectCount} /Root 1 0 R >>\nstartxref\n{$crossReferenceOffset}\n%%EOF\n");
        fclose($pdf);
    }

    /**
     * Decode an image, stand it upright, shrink it to A4 print size, and flatten any transparency onto white.
     */
    private function loadImageForPdf(string $imagePath): GdImage
    {
        $source = @imagecreatefromstring((string) file_get_contents($imagePath));

        if ($source === false) {
            throw new RuntimeException("Image could not be decoded: {$imagePath}");
        }

        $source = $this->applyExifOrientation($source, $imagePath);
        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1, self::MAX_IMAGE_EDGE_PIXELS / max($width, $height));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    /**
     * Phone cameras store sideways photos with a rotation tag rather than rotated pixels.
     */
    private function applyExifOrientation(GdImage $image, string $imagePath): GdImage
    {
        if (! function_exists('exif_read_data') || exif_imagetype($imagePath) !== IMAGETYPE_JPEG) {
            return $image;
        }

        $orientation = @exif_read_data($imagePath)['Orientation'] ?? 1;

        $angle = match ($orientation) {
            3 => 180,
            6 => 270,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : imagerotate($image, $angle, 0);
    }
}
