<?php

use App\Enums\InvoiceStatus;
use App\Invoices\Flag;
use App\Invoices\FlagCode;
use App\Invoices\InvoiceChecker;
use App\Models\Invoice;

/**
 * @return list<string>
 */
function flagCodes(Invoice $invoice): array
{
    return array_map(fn (Flag $flag): string => $flag->code->value, app(InvoiceChecker::class)->check($invoice));
}

it('has nothing to say about a clean invoice', function () {
    $invoice = Invoice::factory()->extracted()->make();

    expect(app(InvoiceChecker::class)->check($invoice))->toBe([]);
});

it('accepts 12% ppn on dpp nilai lain', function () {
    $invoice = Invoice::factory()->extracted()->make([
        'line_items' => [['description' => 'Kardus', 'quantity' => 800, 'unit_price' => 4650, 'amount' => 3720000]],
        'subtotal' => 3720000,
        'tax_base' => 3410000,
        'tax_rate' => '12.00',
        'tax_amount' => 409200,
        'total' => 4129200,
    ]);

    expect(flagCodes($invoice))->toBe([]);
});

it('flags a missing invoice number', function () {
    $invoice = Invoice::factory()->extracted()->make(['invoice_number' => null]);

    expect(flagCodes($invoice))->toBe([FlagCode::MissingInvoiceNumber->value]);
});

it('flags the same number from the same supplier', function () {
    $first = Invoice::factory()->extracted()->create();
    $resend = Invoice::factory()->extracted()->create(['invoice_number' => $first->invoice_number]);

    $flags = app(InvoiceChecker::class)->check($resend);

    expect($flags)->toHaveCount(1)
        ->and($flags[0]->code)->toBe(FlagCode::DuplicateInvoiceNumber)
        ->and($flags[0]->message)->toContain("upload #{$first->id}");
});

it('does not flag the original once the resend arrives', function () {
    $original = Invoice::factory()->extracted()->create();
    Invoice::factory()->extracted()->create(['invoice_number' => $original->invoice_number]);

    expect(flagCodes($original))->toBe([]);
});

it('allows two suppliers to use the same number', function () {
    $first = Invoice::factory()->extracted()->create(['invoice_number' => 'INV-001']);
    $other = Invoice::factory()->extracted()->create(['invoice_number' => 'INV-001', 'supplier_npwp' => '9876543210987000']);

    expect(flagCodes($other))->toBe([]);
});

it('matches suppliers by name when there is no npwp', function () {
    Invoice::factory()->extracted()->create(['invoice_number' => 'A-1', 'supplier_npwp' => null, 'tax_rate' => null, 'tax_amount' => null, 'total' => 3000000]);
    $second = Invoice::factory()->extracted()->create(['invoice_number' => 'A-1', 'supplier_npwp' => null, 'supplier_name' => 'pt sumber kemasan nusantara', 'tax_rate' => null, 'tax_amount' => null, 'total' => 3000000]);

    expect(flagCodes($second))->toBe([FlagCode::DuplicateInvoiceNumber->value]);
});

it('ignores failed uploads when looking for duplicates', function () {
    $failed = Invoice::factory()->extracted()->create(['status' => InvoiceStatus::Failed]);
    $retry = Invoice::factory()->extracted()->create(['invoice_number' => $failed->invoice_number]);

    expect(flagCodes($retry))->toBe([]);
});

it('flags a line whose quantity times price is off', function () {
    $invoice = Invoice::factory()->extracted()->make([
        'line_items' => [
            ['description' => 'Kardus 40x30x30', 'quantity' => 500, 'unit_price' => 4500, 'amount' => 2250000],
            ['description' => 'Lakban bening 2 inch', 'quantity' => 50, 'unit_price' => 15000, 'amount' => 570000],
        ],
        'subtotal' => 2820000,
        'tax_amount' => 310200,
        'total' => 3130200,
    ]);

    $flags = app(InvoiceChecker::class)->check($invoice);

    expect($flags)->toHaveCount(1)
        ->and($flags[0]->code)->toBe(FlagCode::LineItemsMismatch)
        ->and($flags[0]->fields)->toBe(['line_items.1'])
        ->and($flags[0]->message)->toBe('Line 2: 50 x Rp 15.000 is Rp 750.000, but the invoice says Rp 570.000.');
});

it('flags line items that do not add up to the subtotal', function () {
    $invoice = Invoice::factory()->extracted()->make(['subtotal' => 3100000, 'tax_amount' => 341000, 'total' => 3441000]);

    $flags = app(InvoiceChecker::class)->check($invoice);

    expect($flags)->toHaveCount(1)
        ->and($flags[0]->fields)->toBe(['subtotal'])
        ->and($flags[0]->message)->toBe('Line items add up to Rp 3.000.000, but the subtotal says Rp 3.100.000.');
});

it('flags a ppn amount that does not match the rate', function () {
    $invoice = Invoice::factory()->extracted()->make(['tax_amount' => 300000, 'total' => 3300000]);

    $flags = app(InvoiceChecker::class)->check($invoice);

    expect($flags)->toHaveCount(1)
        ->and($flags[0]->code)->toBe(FlagCode::TaxMismatch)
        ->and($flags[0]->message)->toBe('PPN 11% of Rp 3.000.000 is Rp 330.000, but the invoice says Rp 300.000.');
});

it('flags a dpp that is neither the subtotal nor 11/12 of it', function () {
    $invoice = Invoice::factory()->extracted()->make(['tax_base' => 2500000, 'tax_amount' => 275000, 'total' => 3275000]);

    expect(flagCodes($invoice))->toBe([FlagCode::TaxMismatch->value]);
});

it('flags a total that is not subtotal plus ppn', function () {
    $invoice = Invoice::factory()->extracted()->make(['total' => 3300000]);

    expect(flagCodes($invoice))->toBe([FlagCode::TotalMismatch->value]);
});

it('lets one rupiah of rounding through', function () {
    $invoice = Invoice::factory()->extracted()->make(['tax_amount' => 330001, 'total' => 3330001]);

    expect(flagCodes($invoice))->toBe([]);
});

it('checks the npwp format', function (?string $npwp, array $expected) {
    $invoice = Invoice::factory()->extracted()->make(['supplier_npwp' => $npwp]);

    expect(flagCodes($invoice))->toBe($expected);
})->with([
    '16 digits' => ['0123456789012000', []],
    '15 digits' => ['012345678901200', []],
    'too short' => ['12345', [FlagCode::InvalidNpwp->value]],
    'missing while charging ppn' => [null, [FlagCode::InvalidNpwp->value]],
]);

it('stores the flags on the invoice', function () {
    $invoice = Invoice::factory()->extracted()->make(['invoice_number' => null]);

    app(InvoiceChecker::class)->refresh($invoice);

    expect($invoice->flags)->toBe([[
        'code' => 'missing_invoice_number',
        'message' => 'No invoice number found.',
        'fields' => ['invoice_number'],
    ]])->and($invoice->isFlagged('invoice_number'))->toBeTrue();
});
