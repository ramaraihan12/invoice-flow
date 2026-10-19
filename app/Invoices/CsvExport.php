<?php

namespace App\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Writes every approved, not yet exported invoice to one CSV file and marks
 * them exported, so the same invoice never lands in two batches.
 */
class CsvExport
{
    public const DIRECTORY = 'exports';

    private const COLUMNS = [
        'id', 'supplier_name', 'supplier_npwp', 'invoice_number', 'invoice_date', 'due_date',
        'currency', 'subtotal', 'tax_base', 'tax_rate', 'tax_amount', 'total', 'line_count', 'approved_at',
    ];

    /**
     * @return array{path: string, count: int}|null null when there was nothing to export
     */
    public function run(): ?array
    {
        return DB::transaction(function (): ?array {
            $invoices = Invoice::query()
                ->where('status', InvoiceStatus::Approved)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($invoices->isEmpty()) {
                return null;
            }

            $path = self::DIRECTORY.'/invoices-'.now()->format('Y-m-d-His').'.csv';
            Storage::put($path, $this->csv($invoices));

            Invoice::query()->whereKey($invoices->modelKeys())->update([
                'status' => InvoiceStatus::Exported,
                'exported_at' => now(),
            ]);

            return ['path' => $path, 'count' => $invoices->count()];
        });
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    private function csv(Collection $invoices): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, self::COLUMNS, escape: '');

        foreach ($invoices as $invoice) {
            fputcsv($handle, [
                $invoice->id,
                $invoice->supplier_name,
                $invoice->supplier_npwp,
                $invoice->invoice_number,
                $invoice->invoice_date?->toDateString(),
                $invoice->due_date?->toDateString(),
                $invoice->currency,
                $invoice->subtotal,
                $invoice->tax_base,
                $invoice->tax_rate,
                $invoice->tax_amount,
                $invoice->total,
                count($invoice->line_items ?? []),
                $invoice->approved_at?->toIso8601String(),
            ], escape: '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
