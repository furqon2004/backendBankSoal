<?php

namespace App\Jobs;

use App\Models\Material;
use App\Services\PdfParserService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtractPdfMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Retry up to 3 times on failure */
    public int $tries = 3;

    /** @var int Timeout in seconds */
    public int $timeout = 300;

    public function __construct(
        public int $materialId,
        public ?int $questionCount = null
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job:
     * 1. Extract images & audio from the PDF.
     * 2. Update material media_extracted + has_audio flags.
     * 3. Dispatch GenerateAiQuestionsJob with the extracted data.
     */
    public function handle(PdfParserService $pdfParser): void
    {
        $material = Material::find($this->materialId);

        if (! $material || empty($material->pdf_path)) {
            Log::warning("ExtractPdfMediaJob: material #{$this->materialId} not found or has no PDF.");
            // Still try to generate questions from text content
            GenerateAiQuestionsJob::dispatch($this->materialId, $this->questionCount);
            return;
        }

        try {
            Log::info("ExtractPdfMediaJob: extracting media from material #{$this->materialId}");

            // 1. Extract text content from PDF (update material content if empty)
            if (empty(trim($material->content ?? ''))) {
                $text = $pdfParser->extractText($material->pdf_path);
                $material->update(['content' => $text]);
            }

            // 2. Check for audio BEFORE extracting images
            $hasAudio = $pdfParser->hasEmbeddedAudio($material->pdf_path);

            // 3. Extract images
            $images = $pdfParser->extractImages($material->pdf_path, $this->materialId);

            // 4. Extract audio (if present)
            $audioFiles = [];
            if ($hasAudio) {
                $audioFiles = $pdfParser->extractAudio($material->pdf_path, $this->materialId);
                // Re-check: if extraction yielded nothing, set has_audio false
                if (empty($audioFiles)) {
                    $hasAudio = false;
                }
            }

            // 5. Update material flags
            $material->update([
                'has_audio'       => $hasAudio,
                'media_extracted' => true,
            ]);

            Log::info("ExtractPdfMediaJob: done for material #{$this->materialId}. Images: " . count($images) . ", Audio: " . count($audioFiles) . ", has_audio: " . ($hasAudio ? 'yes' : 'no'));

            // 6. Dispatch AI question generation with full context
            GenerateAiQuestionsJob::dispatch($this->materialId, $this->questionCount);

        } catch (Exception $e) {
            Log::error("ExtractPdfMediaJob: failed for material #{$this->materialId}: " . $e->getMessage());

            // Mark as extracted (even if partial) so we don't get stuck
            $material->update(['media_extracted' => true]);

            // Still attempt question generation with whatever we have
            GenerateAiQuestionsJob::dispatch($this->materialId, $this->questionCount);
        }
    }
}
