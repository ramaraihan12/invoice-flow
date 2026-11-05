<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Extraction\DocumentReader;
use App\Extraction\ExtractedInvoice;
use App\Invoices\InvoiceChecker;
use App\Models\Invoice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Puts the public demo back: whatever visitors left goes, and one invoice waits
 * for review, so there is something to look at when the model limit is reached.
 * The rate limit counters stay, a reset is no way around them.
 */
class DemoSeeder extends Seeder
{
    private const TABLES = [
        'webhook_deliveries', 'invoices', 'stored_files', 'demo_webhook_receipts',
        'sessions', 'cache', 'cache_locks', 'jobs', 'failed_jobs', 'job_batches',
    ];

    private const SAMPLE = '03-higienis-prima.pdf';

    private const MODEL = 'gemini:gemini-2.5-flash';

    // What that model really returned for the sample, so a reset needs no API key.
    private const EXTRACTION = [
        'supplier_name' => 'PT Higienis Prima Sejahtera',
        'supplier_npwp' => '0981223344556000',
        'invoice_number' => 'INV/HPS/X/2026/0412',
        'invoice_date' => '2026-10-09',
        'due_date' => '2026-10-30',
        'currency' => 'IDR',
        'line_items' => [
            ['description' => 'Sabun cuci piring jerigen 5 L', 'quantity' => 12, 'unit_price' => 68500, 'amount' => 822000],
            ['description' => 'Cairan desinfektan lantai 4 L', 'quantity' => 10, 'unit_price' => 92000, 'amount' => 920000],
            ['description' => 'Tisu dapur roll isi 2', 'quantity' => 48, 'unit_price' => 21500, 'amount' => 1032000],
            ['description' => 'Sarung tangan nitril (100)', 'quantity' => 15, 'unit_price' => 78000, 'amount' => 1170000],
            ['description' => 'Plastik sampah 90x120 (isi 10)', 'quantity' => 40, 'unit_price' => 16500, 'amount' => 660000],
        ],
        'subtotal' => 4604000,
        'tax_base' => null,
        'tax_rate' => 11,
        'tax_amount' => 504640,
        'total' => 5108640,
    ];

    public function run(DocumentReader $reader, InvoiceChecker $checker): void
    {
        if (! config('invoices.demo.enabled')) {
            throw new RuntimeException('This wipes every invoice, so it only runs with DEMO_MODE=true.');
        }

        DB::transaction(function () use ($reader, $checker): void {
            foreach (self::TABLES as $table) {
                DB::table($table)->truncate();
            }

            $contents = (string) file_get_contents(base_path('samples/'.self::SAMPLE));
            $path = 'invoices/'.self::SAMPLE;

            Storage::put($path, $contents);

            $invoice = new Invoice([
                ...ExtractedInvoice::fromArray(self::EXTRACTION)->toAttributes(),
                'status' => InvoiceStatus::NeedsReview,
                'original_name' => self::SAMPLE,
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'source_text' => $reader->pdfText($contents),
                'raw_extraction' => self::EXTRACTION,
                'extracted_with' => self::MODEL,
                'extracted_at' => now(),
            ]);

            $checker->refresh($invoice);
            $invoice->save();
        });
    }
}
