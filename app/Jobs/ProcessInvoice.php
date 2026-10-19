<?php

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Extraction\DocumentReader;
use App\Extraction\ExtractionFailed;
use App\Extraction\InvoiceExtractor;
use App\Invoices\InvoiceChecker;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessInvoice implements ShouldQueue
{
    use Queueable;

    // One retry covers a model that returned broken JSON or an Ollama restart.
    public int $tries = 2;

    public int $timeout = 600;

    public int $backoff = 30;

    public function __construct(public Invoice $invoice) {}

    public function handle(DocumentReader $reader, InvoiceExtractor $extractor, InvoiceChecker $checker): void
    {
        $invoice = $this->invoice;

        try {
            $document = $reader->read($invoice);
            $result = $extractor->extract($document);
        } catch (ExtractionFailed $e) {
            $this->markFailed($e->getMessage());

            return;
        }

        $invoice->fill([
            ...$result->invoice->toAttributes(),
            'source_text' => $document->text,
            'raw_extraction' => $result->raw,
            'extracted_with' => $result->model,
            'extracted_at' => now(),
            'error' => null,
        ]);

        $checker->refresh($invoice);

        $invoice->status = InvoiceStatus::NeedsReview;
        $invoice->save();
    }

    public function failed(?Throwable $e): void
    {
        $this->markFailed($e?->getMessage() ?? 'Extraction failed.');
    }

    private function markFailed(string $message): void
    {
        $this->invoice->update([
            'status' => InvoiceStatus::Failed,
            'error' => $message,
        ]);
    }
}
