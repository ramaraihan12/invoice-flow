<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * What a well-behaved model returns for samples/01-sinar-kemas-abadi.pdf.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sampleExtraction(array $overrides = []): array
{
    return [
        'supplier_name' => 'PT Sinar Kemas Abadi',
        'supplier_npwp' => '0123 4567 8901 2000',
        'invoice_number' => 'SKA/INV/2026/10/0147',
        'invoice_date' => '2026-10-02',
        'due_date' => '2026-11-01',
        'currency' => 'IDR',
        'line_items' => [
            ['description' => 'Kardus single wall 40x30x30 cm', 'quantity' => 800, 'unit_price' => 4650, 'amount' => 3720000],
            ['description' => 'Lakban coklat 48 mm x 100 yard', 'quantity' => 60, 'unit_price' => 14500, 'amount' => 870000],
            ['description' => 'Bubble wrap 125 cm x 50 m', 'quantity' => 6, 'unit_price' => 187000, 'amount' => 1122000],
            ['description' => 'Stretch film 50 cm x 300 m', 'quantity' => 12, 'unit_price' => 96000, 'amount' => 1152000],
        ],
        'subtotal' => 6864000,
        'tax_base' => 6292000,
        'tax_rate' => 12,
        'tax_amount' => 755040,
        'total' => 7619040,
        ...$overrides,
    ];
}
