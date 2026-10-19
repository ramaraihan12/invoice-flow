<?php

use App\Enums\InvoiceStatus;
use App\Invoices\InvoiceChecker;
use App\Jobs\DeliverWebhook;
use App\Jobs\ProcessInvoice;
use App\Models\Invoice;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
});

/**
 * The review form as the browser posts it, built from an invoice.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function reviewForm(Invoice $invoice, array $overrides = []): array
{
    return [
        'supplier_name' => $invoice->supplier_name,
        'supplier_npwp' => $invoice->supplier_npwp,
        'invoice_number' => $invoice->invoice_number,
        'invoice_date' => $invoice->invoice_date?->toDateString(),
        'due_date' => $invoice->due_date?->toDateString(),
        'items' => $invoice->line_items,
        'subtotal' => $invoice->subtotal,
        'tax_base' => $invoice->tax_base,
        'tax_rate' => $invoice->tax_rate,
        'tax_amount' => $invoice->tax_amount,
        'total' => $invoice->total,
        ...$overrides,
    ];
}

it('lists invoices with counts per status', function () {
    Invoice::factory()->extracted()->count(2)->create();
    Invoice::factory()->approved()->create(['supplier_name' => 'CV Mitra Teknik Mandiri']);
    Invoice::factory()->create(['original_name' => 'still-reading.pdf']);

    $this->get('/invoices')
        ->assertOk()
        ->assertSee('Export approved to CSV (1)')
        ->assertSee('CV Mitra Teknik Mandiri')
        ->assertSee('still-reading.pdf');

    $this->get('/invoices?status=approved')
        ->assertOk()
        ->assertSee('CV Mitra Teknik Mandiri')
        ->assertDontSee('still-reading.pdf');
});

it('shows extracted fields and flags on the review screen', function () {
    $invoice = Invoice::factory()->extracted()->create(['total' => 3300000]);
    app(InvoiceChecker::class)->refresh($invoice);
    $invoice->save();

    $this->get("/invoices/{$invoice->id}")
        ->assertOk()
        ->assertSee($invoice->invoice_number)
        ->assertSee('value="3.300.000"', escape: false)
        ->assertSee('Subtotal plus PPN is Rp 3.330.000, but the total says Rp 3.300.000.')
        ->assertSee('Approve anyway');
});

it('saves corrections typed the indonesian way and checks again', function () {
    $invoice = Invoice::factory()->extracted()->create(['total' => 3300000, 'flags' => [['code' => 'total_mismatch', 'message' => 'x', 'fields' => ['total']]]]);

    $this->put("/invoices/{$invoice->id}", reviewForm($invoice, [
        'total' => 'Rp 3.330.000',
        'supplier_npwp' => '01.234.567.8-901.2000',
        'items' => [
            ['description' => 'Kardus 40x30x30', 'quantity' => '500', 'unit_price' => '4.500', 'amount' => '2.250.000'],
            ['description' => 'Lakban bening 2 inch', 'quantity' => '50', 'unit_price' => '15.000', 'amount' => '750.000,00'],
            ['description' => '', 'quantity' => '', 'unit_price' => '', 'amount' => ''],
        ],
    ]))->assertRedirect("/invoices/{$invoice->id}");

    $invoice->refresh();

    expect($invoice->total)->toBe(3330000)
        ->and($invoice->supplier_npwp)->toBe('0123456789012000')
        ->and($invoice->line_items)->toHaveCount(2)
        ->and($invoice->line_items[1])->toBe(['description' => 'Lakban bening 2 inch', 'quantity' => 50, 'unit_price' => 15000, 'amount' => 750000])
        ->and($invoice->flags)->toBe([])
        ->and($invoice->status)->toBe(InvoiceStatus::NeedsReview);
});

it('rejects input that is not a number', function () {
    $invoice = Invoice::factory()->extracted()->create();

    $this->put("/invoices/{$invoice->id}", reviewForm($invoice, ['total' => 'tiga juta']))
        ->assertSessionHasErrors('total');
});

it('shows line item errors on the review page', function () {
    $invoice = Invoice::factory()->extracted()->create();
    $items = $invoice->line_items;
    $items[0]['amount'] = 'abc';

    $this->from("/invoices/{$invoice->id}")
        ->put("/invoices/{$invoice->id}", reviewForm($invoice, ['items' => $items]))
        ->assertRedirect("/invoices/{$invoice->id}")
        ->assertSessionHasErrors('items.0.amount');

    $this->get("/invoices/{$invoice->id}")->assertSee('border-rose-400', false);
});

it('accepts a negative discount line', function () {
    $invoice = Invoice::factory()->extracted()->create();
    $items = [...$invoice->line_items, ['description' => 'Diskon', 'quantity' => 1, 'unit_price' => '-50.000', 'amount' => '-50.000']];

    $this->put("/invoices/{$invoice->id}", reviewForm($invoice, ['items' => $items]))
        ->assertSessionHasNoErrors();

    expect(collect($invoice->refresh()->line_items)->last()['amount'])->toBe(-50000);
});

it('approves a clean invoice and sends the webhook', function () {
    config(['invoices.webhook.url' => 'https://hooks.example.test/invoices']);
    $invoice = Invoice::factory()->extracted()->create();

    $this->post("/invoices/{$invoice->id}/approve", reviewForm($invoice))
        ->assertRedirect('/invoices')
        ->assertSessionHas('status', "Approved {$invoice->invoice_number}.");

    $invoice->refresh();
    $delivery = $invoice->webhookDeliveries()->sole();

    expect($invoice->status)->toBe(InvoiceStatus::Approved)
        ->and($invoice->approved_at)->not->toBeNull()
        ->and($delivery->event)->toBe('invoice.approved')
        ->and($delivery->status)->toBe(WebhookDelivery::PENDING);

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->delivery->is($delivery));
});

it('skips the webhook when none is configured', function () {
    config(['invoices.webhook.url' => null]);
    $invoice = Invoice::factory()->extracted()->create();

    $this->post("/invoices/{$invoice->id}/approve", reviewForm($invoice));

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Approved)
        ->and(WebhookDelivery::count())->toBe(0);

    Queue::assertNotPushed(DeliverWebhook::class);
});

it('moves on to the next invoice waiting for review', function () {
    $invoice = Invoice::factory()->extracted()->create();
    $next = Invoice::factory()->extracted()->create();

    $this->post("/invoices/{$invoice->id}/approve", reviewForm($invoice))
        ->assertRedirect("/invoices/{$next->id}");
});

it('will not approve with open flags unless told to', function () {
    $invoice = Invoice::factory()->extracted()->create(['tax_amount' => 300000, 'total' => 3300000]);

    $this->post("/invoices/{$invoice->id}/approve", reviewForm($invoice))
        ->assertRedirect("/invoices/{$invoice->id}")
        ->assertSessionHas('error');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::NeedsReview)
        ->and($invoice->flags)->toHaveCount(1);

    $this->post("/invoices/{$invoice->id}/approve", reviewForm($invoice, ['accept_flags' => '1']))
        ->assertRedirect('/invoices');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Approved);
});

it('locks invoices once they are approved', function () {
    $invoice = Invoice::factory()->approved()->create();

    $this->put("/invoices/{$invoice->id}", reviewForm($invoice, ['total' => 1]))->assertConflict()->assertSee('Only invoices waiting for review can be edited.');
    $this->post("/invoices/{$invoice->id}/approve", reviewForm($invoice))->assertConflict();
    $this->delete("/invoices/{$invoice->id}")->assertConflict();

    expect($invoice->refresh()->total)->toBe(3330000);
});

it('queues a failed invoice again', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Failed, 'error' => 'Ollama was down']);

    $this->post("/invoices/{$invoice->id}/retry")->assertRedirect("/invoices/{$invoice->id}");

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Processing)
        ->and($invoice->error)->toBeNull();

    Queue::assertPushed(ProcessInvoice::class);
});

it('deletes an upload and its file', function () {
    Storage::fake();
    Storage::put('invoices/a.pdf', 'pdf');
    $invoice = Invoice::factory()->extracted()->create(['file_path' => 'invoices/a.pdf']);

    $this->delete("/invoices/{$invoice->id}")->assertRedirect('/invoices');

    expect(Invoice::count())->toBe(0);
    Storage::assertMissing('invoices/a.pdf');
});

it('serves the original file inline', function () {
    Storage::fake();
    Storage::put('invoices/a.pdf', '%PDF-1.7');
    $invoice = Invoice::factory()->create(['file_path' => 'invoices/a.pdf', 'original_name' => 'nota.pdf']);

    $this->get("/invoices/{$invoice->id}/file")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=nota.pdf');
});

it('reports line item errors under the row the reviewer typed in', function () {
    $invoice = Invoice::factory()->extracted()->create();
    $items = [
        1 => ['description' => 'Lakban', 'quantity' => 1, 'unit_price' => 1000, 'amount' => 'abc'],
        3 => ['description' => 'Kardus', 'quantity' => 1, 'unit_price' => 1000, 'amount' => 1000],
    ];

    $response = $this->from("/invoices/{$invoice->id}")
        ->put("/invoices/{$invoice->id}", reviewForm($invoice, ['items' => $items]))
        ->assertSessionHasErrors('items.1.amount');

    expect(session('errors')->first('items.1.amount'))->toBe('The line item amount field must be an integer.');
});
