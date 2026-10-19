<?php

namespace App\Extraction;

final readonly class ExtractionResult
{
    /**
     * @param  array<mixed>  $raw  untouched model output, kept for debugging bad extractions
     */
    public function __construct(
        public ExtractedInvoice $invoice,
        public array $raw,
        public string $model,
    ) {}
}
