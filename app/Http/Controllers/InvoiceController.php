<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\StoreInvoicesRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Invoices\InvoiceChecker;
use App\Jobs\DeliverWebhook;
use App\Jobs\ProcessInvoice;
use App\Models\Invoice;
use App\Models\WebhookDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::enum(InvoiceStatus::class)]]);

        $status = $request->enum('status', InvoiceStatus::class);

        $counts = Invoice::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $invoices = Invoice::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'counts' => $counts,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('invoices.create', [
            'acceptsPhotos' => (bool) config('invoices.llm.vision'),
        ]);
    }

    public function store(StoreInvoicesRequest $request): RedirectResponse
    {
        $files = $request->uploadedFiles();

        foreach ($files as $file) {
            $invoice = Invoice::create([
                'status' => InvoiceStatus::Processing,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $file->store('invoices'),
                'mime_type' => $file->getMimeType(),
            ]);

            ProcessInvoice::dispatch($invoice);
        }

        return to_route('invoices.index')
            ->with('status', trans_choice('{1} Uploaded 1 invoice, it is being read now.|[2,*] Uploaded :count invoices, they are being read now.', count($files)));
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['webhookDeliveries' => fn ($query) => $query->latest('id')]);

        return view('invoices.show', ['invoice' => $invoice]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, InvoiceChecker $checker): RedirectResponse
    {
        abort_unless($invoice->isEditable(), 409, 'Only invoices waiting for review can be edited.');

        $invoice->fill($request->invoiceAttributes());
        $checker->refresh($invoice);
        $invoice->save();

        return to_route('invoices.show', $invoice)->with('status', 'Saved. Checks were run again.');
    }

    public function approve(UpdateInvoiceRequest $request, Invoice $invoice, InvoiceChecker $checker): RedirectResponse
    {
        abort_unless($invoice->isEditable(), 409, 'Only invoices waiting for review can be approved.');

        $invoice->fill($request->invoiceAttributes());
        $checker->refresh($invoice);

        $openFlags = count($invoice->flags ?? []);

        if ($openFlags > 0 && ! $request->boolean('accept_flags')) {
            $invoice->save();

            return to_route('invoices.show', $invoice)->with(
                'error',
                trans_choice('{1} There is still 1 open flag. Fix it, or tick "approve anyway" if the supplier really printed it that way.|[2,*] There are still :count open flags. Fix them, or tick "approve anyway" if the supplier really printed it that way.', $openFlags),
            );
        }

        DB::transaction(function () use ($invoice): void {
            // A double click sends two requests at once, so check again under a lock.
            $current = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->first();
            abort_unless($current?->isEditable(), 409, 'This invoice was already approved or changed. Reload the page.');

            $invoice->status = InvoiceStatus::Approved;
            $invoice->approved_at = now();
            $invoice->save();

            $url = config('invoices.webhook.url');

            if (filled($url)) {
                $delivery = $invoice->webhookDeliveries()->create([
                    'uuid' => (string) Str::uuid(),
                    'event' => 'invoice.approved',
                    'url' => $url,
                    'status' => WebhookDelivery::PENDING,
                ]);

                DeliverWebhook::dispatch($delivery)->afterCommit();
            }
        });

        $next = Invoice::query()
            ->where('status', InvoiceStatus::NeedsReview)
            ->oldest('id')
            ->first();

        $redirect = $next ? to_route('invoices.show', $next) : to_route('invoices.index');

        return $redirect->with('status', "Approved {$this->label($invoice)}.");
    }

    public function retry(Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status === InvoiceStatus::Failed, 409, 'Only failed invoices can be retried.');

        $invoice->update(['status' => InvoiceStatus::Processing, 'error' => null]);

        ProcessInvoice::dispatch($invoice);

        return to_route('invoices.show', $invoice)->with('status', 'Queued again.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        abort_if(in_array($invoice->status, [InvoiceStatus::Approved, InvoiceStatus::Exported], true), 409, 'Approved invoices are kept.');

        Storage::delete($invoice->file_path);
        $invoice->delete();

        return to_route('invoices.index')->with('status', "Deleted {$this->label($invoice)}.");
    }

    public function file(Invoice $invoice): StreamedResponse
    {
        abort_unless(Storage::exists($invoice->file_path), 404);

        return Storage::response($invoice->file_path, $invoice->original_name, [
            'Content-Type' => $invoice->mime_type,
        ], 'inline');
    }

    private function label(Invoice $invoice): string
    {
        return $invoice->invoice_number ?? $invoice->original_name;
    }
}
