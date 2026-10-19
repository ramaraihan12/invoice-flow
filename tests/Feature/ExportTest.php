<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

it('writes approved invoices to a csv and marks them exported', function () {
    $approved = Invoice::factory()->approved()->count(2)->create();
    $waiting = Invoice::factory()->extracted()->create();

    $response = $this->post('/exports')
        ->assertRedirect()
        ->assertSessionHas('status', 'Exported 2 invoices.')
        ->assertSessionHas('download');

    $file = session('download');
    $lines = array_map(str_getcsv(...), explode("\n", trim(Storage::get("exports/{$file}"))));

    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toBe(['id', 'supplier_name', 'supplier_npwp', 'invoice_number', 'invoice_date', 'due_date', 'currency', 'subtotal', 'tax_base', 'tax_rate', 'tax_amount', 'total', 'line_count', 'approved_at'])
        ->and($lines[1][0])->toBe((string) $approved[0]->id)
        ->and($lines[1][3])->toBe($approved[0]->invoice_number)
        ->and(array_slice($lines[1], 4, 9))->toBe(['2026-10-01', '2026-10-31', 'IDR', '3000000', '', '11.00', '330000', '3330000', '2']);

    expect($approved[0]->refresh()->status)->toBe(InvoiceStatus::Exported)
        ->and($approved[0]->exported_at)->not->toBeNull()
        ->and($waiting->refresh()->status)->toBe(InvoiceStatus::NeedsReview);

    $this->get("/exports/{$file}")
        ->assertOk()
        ->assertDownload($file);
});

it('never exports the same invoice twice', function () {
    Invoice::factory()->approved()->create();

    $this->post('/exports')->assertSessionHas('status');
    $this->post('/exports')->assertSessionHas('error', 'Nothing to export, there are no approved invoices waiting.');

    expect(Storage::files('exports'))->toHaveCount(1);
});

it('only serves files from the export folder', function () {
    Storage::put('invoices/secret.pdf', 'x');

    $this->get('/exports/..%2Finvoices%2Fsecret.pdf')->assertNotFound();
    $this->get('/exports/invoices-2026-01-01-000000.csv')->assertNotFound();
});
