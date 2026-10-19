<?php

use App\Extraction\ExtractedInvoice;

it('turns model output into clean attributes', function () {
    $invoice = ExtractedInvoice::fromArray(sampleExtraction());

    expect($invoice->toAttributes())->toMatchArray([
        'supplier_name' => 'PT Sinar Kemas Abadi',
        'supplier_npwp' => '0123456789012000',
        'invoice_number' => 'SKA/INV/2026/10/0147',
        'invoice_date' => '2026-10-02',
        'due_date' => '2026-11-01',
        'currency' => 'IDR',
        'subtotal' => 6864000,
        'tax_base' => 6292000,
        'tax_rate' => 12.0,
        'tax_amount' => 755040,
        'total' => 7619040,
    ])->and($invoice->lineItems)->toHaveCount(4);
});

it('parses amounts the model returned as indonesian strings', function () {
    $invoice = ExtractedInvoice::fromArray(sampleExtraction([
        'subtotal' => 'Rp 6.864.000',
        'line_items' => [['description' => 'Kardus', 'quantity' => '800', 'unit_price' => '4.650', 'amount' => '3.720.000,00']],
    ]));

    expect($invoice->subtotal)->toBe(6864000)
        ->and($invoice->lineItems[0])->toBe(['description' => 'Kardus', 'quantity' => 800, 'unit_price' => 4650, 'amount' => 3720000]);
});

it('keeps wrong numbers as printed instead of fixing them', function () {
    $invoice = ExtractedInvoice::fromArray(sampleExtraction(['total' => 9999999]));

    expect($invoice->total)->toBe(9999999);
});

it('drops values it cannot trust', function () {
    $invoice = ExtractedInvoice::fromArray([
        'supplier_name' => '  ',
        'supplier_npwp' => 'tidak ada',
        'invoice_date' => '2 Oktober 2026',
        'due_date' => '2026-02-30',
        'currency' => null,
        'line_items' => ['not a row', ['description' => 'Ongkir', 'quantity' => 1.5]],
    ]);

    expect($invoice->supplierName)->toBeNull()
        ->and($invoice->supplierNpwp)->toBeNull()
        ->and($invoice->invoiceDate)->toBeNull()
        ->and($invoice->dueDate)->toBeNull()
        ->and($invoice->currency)->toBe('IDR')
        ->and($invoice->lineItems)->toBe([['description' => 'Ongkir', 'quantity' => 1.5, 'unit_price' => null, 'amount' => null]]);
});

it('falls back to IDR when the currency is not a three letter code', function () {
    expect(ExtractedInvoice::fromArray(sampleExtraction(['currency' => 'Rupiah']))->currency)->toBe('IDR')
        ->and(ExtractedInvoice::fromArray(sampleExtraction(['currency' => 'usd']))->currency)->toBe('USD')
        ->and(ExtractedInvoice::fromArray(sampleExtraction(['currency' => null]))->currency)->toBe('IDR');
});

it('drops a tax rate outside 0 to 100', function () {
    expect(ExtractedInvoice::fromArray(sampleExtraction(['tax_rate' => 1200]))->taxRate)->toBeNull()
        ->and(ExtractedInvoice::fromArray(sampleExtraction(['tax_rate' => 11]))->taxRate)->toBe(11.0);
});
