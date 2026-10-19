<?php

use App\Enums\InvoiceStatus;
use App\Jobs\ProcessInvoice;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Structured\Request as StructuredRequest;
use Prism\Prism\Testing\StructuredResponseFake;

beforeEach(function () {
    Storage::fake();
});

function uploadedSample(string $file = '01-sinar-kemas-abadi.pdf'): Invoice
{
    Storage::put("invoices/{$file}", file_get_contents(base_path("samples/{$file}")));

    return Invoice::factory()->create([
        'original_name' => $file,
        'file_path' => "invoices/{$file}",
    ]);
}

it('reads the pdf, extracts the fields and queues it for review', function () {
    $prism = Prism::fake([StructuredResponseFake::make()->withStructured(sampleExtraction())]);
    $invoice = uploadedSample();

    ProcessInvoice::dispatch($invoice);

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::NeedsReview)
        ->and($invoice->supplier_name)->toBe('PT Sinar Kemas Abadi')
        ->and($invoice->supplier_npwp)->toBe('0123456789012000')
        ->and($invoice->invoice_number)->toBe('SKA/INV/2026/10/0147')
        ->and($invoice->due_date->toDateString())->toBe('2026-11-01')
        ->and($invoice->total)->toBe(7619040)
        ->and($invoice->line_items)->toHaveCount(4)
        ->and($invoice->flags)->toBe([])
        ->and($invoice->extracted_with)->toBe('ollama:'.config('invoices.llm.model'))
        ->and($invoice->source_text)->toContain('Jatuh Tempo : 1 November 2026');

    $prism->assertCallCount(1);
    $prism->assertRequest(function (array $requests) {
        /** @var StructuredRequest $request */
        $request = $requests[0];

        expect($request->prompt())->toContain('SKA/INV/2026/10/0147')
            ->and($request->temperature())->toBe(0)
            ->and($request->providerOptions())->toBe(['num_ctx' => 8192]);
    });
});

it('flags the mistakes in the extracted data', function () {
    Prism::fake([StructuredResponseFake::make()->withStructured(sampleExtraction(['tax_amount' => 750000, 'total' => 7614000]))]);
    $invoice = uploadedSample();

    ProcessInvoice::dispatch($invoice);

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::NeedsReview)
        ->and(array_column($invoice->flags, 'code'))->toBe(['tax_mismatch']);
});

it('sends no ollama options to other providers', function () {
    config(['invoices.llm.provider' => 'openai', 'invoices.llm.model' => 'gpt-5-mini']);
    $prism = Prism::fake([StructuredResponseFake::make()->withStructured(sampleExtraction())]);

    ProcessInvoice::dispatch(uploadedSample());

    $prism->assertRequest(function (array $requests) {
        expect($requests[0]->provider())->toBe('openai')
            ->and($requests[0]->providerOptions())->toBe([]);
    });
});

it('fails with a clear message when the model returns nothing', function () {
    Prism::fake([StructuredResponseFake::make()->withStructured([])->withFinishReason(FinishReason::Length)]);
    $invoice = uploadedSample();

    ProcessInvoice::dispatch($invoice);

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Failed)
        ->and($invoice->error)->toBe('The model stopped before returning any data (Length).');
});

it('fails a pdf without a text layer before calling the model', function () {
    $prism = Prism::fake();
    Storage::put('invoices/scan.pdf', file_get_contents(base_path('tests/fixtures/scan.pdf')));
    $invoice = Invoice::factory()->create(['file_path' => 'invoices/scan.pdf']);

    ProcessInvoice::dispatch($invoice);

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Failed)
        ->and($invoice->error)->toContain('no text layer');

    $prism->assertCallCount(0);
});

it('refuses photos when the model cannot read images', function () {
    config(['invoices.llm.vision' => false]);
    $prism = Prism::fake();
    Storage::put('invoices/photo.jpg', 'jpeg bytes');
    $invoice = Invoice::factory()->create(['file_path' => 'invoices/photo.jpg', 'mime_type' => 'image/jpeg']);

    ProcessInvoice::dispatch($invoice);

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Failed)
        ->and($invoice->error)->toContain('INVOICE_LLM_VISION');

    $prism->assertCallCount(0);
});

it('sends photos to a vision model as an image', function () {
    config(['invoices.llm.vision' => true]);
    $prism = Prism::fake([StructuredResponseFake::make()->withStructured(sampleExtraction())]);
    Storage::put('invoices/photo.png', file_get_contents(base_path('tests/fixtures/nota.png')));
    $invoice = Invoice::factory()->create(['file_path' => 'invoices/photo.png', 'mime_type' => 'image/png']);

    ProcessInvoice::dispatch($invoice);

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::NeedsReview)
        ->and($invoice->source_text)->toBeNull();

    $prism->assertRequest(function (array $requests) {
        $images = $requests[0]->messages()[0]->images();

        expect($images)->toHaveCount(1)
            ->and($images[0]->mimeType())->toBe('image/png');
    });
});

it('marks the invoice failed when the provider keeps erroring', function () {
    $job = new ProcessInvoice($invoice = uploadedSample());

    $job->failed(new RuntimeException('Connection refused'));

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Failed)
        ->and($invoice->error)->toBe('Connection refused');
});
