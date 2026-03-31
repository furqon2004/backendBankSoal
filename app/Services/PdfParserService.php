<?php

namespace App\Services;

use App\Models\MaterialMedia;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class PdfParserService
{
    protected Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Extract full text content from a PDF file.
     *
     * @param string $pdfPath Storage path (relative to storage/app)
     * @throws Exception
     */
    public function extractText(string $pdfPath): string
    {
        $fullPath = Storage::path($pdfPath);

        if (! file_exists($fullPath)) {
            throw new Exception("PDF file not found: {$pdfPath}");
        }

        $pdf = $this->parser->parseFile($fullPath);
        $text = $pdf->getText();

        if (empty(trim($text))) {
            Log::warning("PdfParser: extracted empty text from {$pdfPath}");
        }

        return $text;
    }

    /**
     * Check if the PDF has any embedded audio files.
     *
     * @param string $pdfPath Storage path
     */
    public function hasEmbeddedAudio(string $pdfPath): bool
    {
        try {
            $fullPath = Storage::path($pdfPath);

            if (! file_exists($fullPath)) {
                return false;
            }

            $pdf = $this->parser->parseFile($fullPath);
            $details = $pdf->getDetails();

            // Check for embedded files in PDF details
            if (isset($details['EmbeddedFiles']) && ! empty($details['EmbeddedFiles'])) {
                return true;
            }

            // Also scan raw PDF content for audio file references
            $rawContent = file_get_contents($fullPath);
            $audioExtensions = ['.mp3', '.wav', '.m4a', '.aac', '.ogg', '.flac'];
            foreach ($audioExtensions as $ext) {
                if (stripos($rawContent, $ext) !== false) {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            Log::warning("PdfParser: could not check for audio in {$pdfPath}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract embedded images from a PDF and save them to storage.
     *
     * Returns an array of MaterialMedia-compatible records saved to DB.
     *
     * @param string $pdfPath Storage path
     * @param int    $materialId
     * @return array List of saved MaterialMedia models
     * @throws Exception
     */
    public function extractImages(string $pdfPath, int $materialId): array
    {
        $fullPath = Storage::path($pdfPath);

        if (! file_exists($fullPath)) {
            throw new Exception("PDF file not found: {$pdfPath}");
        }

        $pdf = $this->parser->parseFile($fullPath);
        $savedMedia = [];
        $order = 0;

        foreach ($pdf->getPages() as $pageNumber => $page) {
            try {
                $pageImages = $page->getXObjects();

                foreach ($pageImages as $name => $xObject) {
                    // Only process image XObjects
                    if (! method_exists($xObject, 'getContent')) {
                        continue;
                    }

                    $content = $xObject->getContent();

                    if (empty($content)) {
                        continue;
                    }

                    // Try to determine image type from content header
                    $extension = $this->detectImageExtension($content);
                    if (! $extension) {
                        continue;
                    }

                    // Skip tiny images (likely decorative borders/bullets < 1KB)
                    if (strlen($content) < 1024) {
                        continue;
                    }

                    $storagePath = "materials/{$materialId}/images/{$name}_{$order}.{$extension}";
                    Storage::put($storagePath, $content);

                    $media = MaterialMedia::create([
                        'material_id' => $materialId,
                        'type'        => 'image',
                        'file_path'   => $storagePath,
                        'file_url'    => Storage::url($storagePath),
                        'page_number' => $pageNumber + 1,
                        'order'       => $order,
                    ]);

                    $savedMedia[] = $media;
                    $order++;
                }
            } catch (Exception $e) {
                Log::warning("PdfParser: failed to extract image from page {$pageNumber}: " . $e->getMessage());
                continue;
            }
        }

        Log::info("PdfParser: extracted " . count($savedMedia) . " images from material #{$materialId}");

        return $savedMedia;
    }

    /**
     * Extract embedded audio files from a PDF and save them to storage.
     *
     * @param string $pdfPath Storage path
     * @param int    $materialId
     * @return array List of saved MaterialMedia models
     * @throws Exception
     */
    public function extractAudio(string $pdfPath, int $materialId): array
    {
        $fullPath = Storage::path($pdfPath);

        if (! file_exists($fullPath)) {
            throw new Exception("PDF file not found: {$pdfPath}");
        }

        $savedMedia = [];
        $order = 0;

        // PDF audio is stored as embedded file attachments
        // We parse the raw binary PDF for embedded audio streams
        $rawContent = file_get_contents($fullPath);

        $audioSignatures = [
            'mp3'  => "\xFF\xFB",    // MP3 frame sync
            'mp3a' => "\xFF\xF3",    // MP3 (another variant)
            'mp3b' => "\xFF\xF2",    // MP3 (another variant)
            'wav'  => "RIFF",        // WAV header
            'ogg'  => "OggS",        // OGG header
            'm4a'  => "ftyp",        // M4A/MP4 header (offset 4)
        ];

        $found = false;
        foreach ($audioSignatures as $type => $sig) {
            if (strpos($rawContent, $sig) !== false) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            Log::info("PdfParser: no embedded audio signatures found in material #{$materialId}");
            return [];
        }

        // Use smalot parser to look for EmbeddedFiles
        try {
            $pdf = $this->parser->parseFile($fullPath);
            $details = $pdf->getDetails();

            if (isset($details['EmbeddedFiles'])) {
                foreach ((array) $details['EmbeddedFiles'] as $idx => $embedded) {
                    if (! isset($embedded['content'])) {
                        continue;
                    }

                    $extension = $this->detectAudioExtension($embedded['content']);
                    if (! $extension) {
                        continue;
                    }

                    $filename = $embedded['name'] ?? "audio_{$order}";
                    $storagePath = "materials/{$materialId}/audio/{$filename}.{$extension}";
                    Storage::put($storagePath, $embedded['content']);

                    $media = MaterialMedia::create([
                        'material_id' => $materialId,
                        'type'        => 'audio',
                        'file_path'   => $storagePath,
                        'file_url'    => Storage::url($storagePath),
                        'page_number' => null,
                        'order'       => $order,
                    ]);

                    $savedMedia[] = $media;
                    $order++;
                }
            }
        } catch (Exception $e) {
            Log::warning("PdfParser: audio extraction error for material #{$materialId}: " . $e->getMessage());
        }

        Log::info("PdfParser: extracted " . count($savedMedia) . " audio files from material #{$materialId}");

        return $savedMedia;
    }

    /**
     * Detect image extension from binary content.
     */
    protected function detectImageExtension(string $content): ?string
    {
        // Check common image file signatures (magic bytes)
        if (str_starts_with($content, "\xFF\xD8\xFF")) return 'jpg';
        if (str_starts_with($content, "\x89PNG\r\n\x1a\n")) return 'png';
        if (str_starts_with($content, "GIF87a") || str_starts_with($content, "GIF89a")) return 'gif';
        if (str_starts_with($content, "BM")) return 'bmp';
        if (str_starts_with($content, "RIFF") && substr($content, 8, 4) === 'WEBP') return 'webp';

        return null;
    }

    /**
     * Detect audio extension from binary content.
     */
    protected function detectAudioExtension(string $content): ?string
    {
        if (str_starts_with($content, "\xFF\xFB") || str_starts_with($content, "\xFF\xF3")) return 'mp3';
        if (str_starts_with($content, "RIFF")) return 'wav';
        if (str_starts_with($content, "OggS")) return 'ogg';
        if (substr($content, 4, 4) === 'ftyp') return 'm4a';

        return null;
    }
}
