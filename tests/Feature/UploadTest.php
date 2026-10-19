<?php

use App\Enums\InvoiceStatus;
use App\Jobs\ProcessInvoice;
use App\Models\Invoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Queue::fake();
});

function photo(): UploadedFile
{
    return new UploadedFile(base_path('tests/fixtures/nota.png'), 'nota.png', 'image/png', test: true);
}

function samplePdf(string $name = '01-sinar-kemas-abadi.pdf'): UploadedFile
{
    return new UploadedFile(base_path("samples/{$name}"), $name, 'application/pdf', test: true);
}

it('shows the upload page', function () {
    $this->get('/invoices/upload')->assertOk()->assertSee('Upload invoices');
});

it('stores each pdf and queues it for extraction', function () {
    $this->post('/invoices', ['files' => [samplePdf(), samplePdf('02-mitra-teknik-mandiri.pdf')]])
        ->assertRedirect('/invoices')
        ->assertSessionHas('status', 'Uploaded 2 invoices, they are being read now.');

    expect(Invoice::count())->toBe(2);

    $invoice = Invoice::query()->where('original_name', '01-sinar-kemas-abadi.pdf')->sole();

    expect($invoice->status)->toBe(InvoiceStatus::Processing)
        ->and($invoice->mime_type)->toBe('application/pdf');

    Storage::assertExists($invoice->file_path);
    Queue::assertPushed(ProcessInvoice::class, 2);
});

it('needs at least one file', function () {
    $this->post('/invoices', [])->assertSessionHasErrors(['files' => 'Pick at least one invoice.']);
});

it('rejects other file types', function () {
    $this->post('/invoices', ['files' => [UploadedFile::fake()->create('invoice.xlsx', 10)]])
        ->assertSessionHasErrors('files.0');

    Queue::assertNothingPushed();
});

it('turns photos away when the model has no vision', function () {
    config(['invoices.llm.vision' => false]);

    $this->post('/invoices', ['files' => [photo()]])
        ->assertSessionHasErrors(['files.0' => "nota.png is a photo, but the configured model can't read images. Upload the PDF instead."]);
});

it('takes photos when the model can read them', function () {
    config(['invoices.llm.vision' => true]);

    $this->post('/invoices', ['files' => [photo()]])->assertRedirect('/invoices');

    expect(Invoice::sole()->mime_type)->toBe('image/png');
});
