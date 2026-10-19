<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status' => InvoiceStatus::Processing,
            'original_name' => 'invoice.pdf',
            'file_path' => 'invoices/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
        ];
    }

    /**
     * A clean extraction: two lines, 11% PPN, everything adds up.
     */
    public function extracted(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::NeedsReview,
            'supplier_name' => 'PT Sumber Kemasan Nusantara',
            'supplier_npwp' => '0123456789012000',
            'invoice_number' => 'INV/2026/10/'.fake()->unique()->numerify('####'),
            'invoice_date' => '2026-10-01',
            'due_date' => '2026-10-31',
            'line_items' => [
                ['description' => 'Kardus 40x30x30', 'quantity' => 500, 'unit_price' => 4_500, 'amount' => 2_250_000],
                ['description' => 'Lakban bening 2 inch', 'quantity' => 50, 'unit_price' => 15_000, 'amount' => 750_000],
            ],
            'subtotal' => 3_000_000,
            'tax_base' => null,
            'tax_rate' => '11.00',
            'tax_amount' => 330_000,
            'total' => 3_330_000,
            'flags' => [],
            'extracted_with' => 'ollama:qwen3.5:9b',
            'extracted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->extracted()->state(fn (): array => [
            'status' => InvoiceStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
