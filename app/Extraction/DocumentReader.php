<?php

namespace App\Extraction;

use App\Models\Invoice;
use Exception;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\ValueObjects\Media\Image;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

class DocumentReader
{
    // Anything shorter is a scan with a stray header or footer, not a real text layer.
    private const MIN_TEXT_LENGTH = 40;

    public function read(Invoice $invoice): InvoiceDocument
    {
        $contents = Storage::get($invoice->file_path);

        if ($contents === null) {
            throw new ExtractionFailed('The uploaded file is missing from storage.');
        }

        if ($invoice->mime_type === 'application/pdf') {
            return InvoiceDocument::fromText($this->pdfText($contents));
        }

        if ($invoice->isImage()) {
            if (! config('invoices.llm.vision')) {
                throw new ExtractionFailed('Photos need a model that can read images. Set INVOICE_LLM_VISION=true with a vision model, or upload a PDF.');
            }

            return InvoiceDocument::fromImage(Image::fromRawContent($contents, $invoice->mime_type));
        }

        throw new ExtractionFailed("Unsupported file type: {$invoice->mime_type}.");
    }

    private function pdfText(string $contents): string
    {
        $config = new Config;
        $config->setRetainImageContent(false);

        try {
            $text = (new Parser([], $config))->parseContent($contents)->getText();
        } catch (Exception $e) {
            throw new ExtractionFailed('Could not read this PDF: '.$e->getMessage(), previous: $e);
        }

        $text = $this->tidy($text);

        if (mb_strlen($text) < self::MIN_TEXT_LENGTH) {
            throw new ExtractionFailed('This PDF has no text layer, it is probably a scan. Upload a photo of it instead (needs a vision model).');
        }

        return $text;
    }

    private function tidy(string $text): string
    {
        $lines = preg_split('/\R/u', $text) ?: [];

        $lines = array_map(
            fn (string $line): string => trim((string) preg_replace('/[ \t]+/u', ' ', $line)),
            $lines,
        );

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }
}
