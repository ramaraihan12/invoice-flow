<?php

namespace App\Http\Controllers;

use App\Demo\ExtractionQuota;
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
use Throwable;

class InvoiceController extends Controller
{
    /**
     * Vercel requests / on every deploy, so on the demo it answers by itself.
     */
    public function home(Request $request): View|RedirectResponse
    {
        return config('invoices.demo.enabled') ? $this->index($request) : to_route('invoices.index');
    }

    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::enum(InvoiceStatus::class)]]);

        $this->failStalled();

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

    public function store(StoreInvoicesRequest $request, ExtractionQuota $quota): RedirectResponse
    {
        $quota->hitShared();

        $files = $request->uploadedFiles();

        foreach ($files as $file) {
            $invoice = Invoice::create([
                'status' => InvoiceStatus::Processing,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'file_path' => $file->store('invoices'),
                'mime_type' => $file->getMimeType(),
            ]);

            $this->read($invoice);
        }

        if (config('invoices.demo.enabled')) {
            return $this->afterReading($invoice);
        }

        return to_route('invoices.index')
            ->with('status', trans_choice('{1} Uploaded 1 invoice, it is being read now.|[2,*] Uploaded :count invoices, they are being read now.', count($files)));
    }

    /**
     * The demo's one-click way in. Only names from the config get this far, so
     * a visitor can't point it at any other file.
     */
    public function storeSample(Request $request, ExtractionQuota $quota): RedirectResponse
    {
        $name = $request->validate([
            'sample' => ['required', 'string', Rule::in(array_keys(config('invoices.demo.samples')))],
        ])['sample'];

        $quota->hitShared();

        $path = 'invoices/'.Str::random(40).'.pdf';
        Storage::put($path, (string) file_get_contents(base_path("samples/{$name}")));

        $invoice = Invoice::create([
            'status' => InvoiceStatus::Processing,
            'original_name' => $name,
            'file_path' => $path,
            'mime_type' => 'application/pdf',
        ]);

        $this->read($invoice);

        return $this->afterReading($invoice);
    }

    public function show(Invoice $invoice): View
    {
        $this->failStalled();

        $invoice->refresh()->load(['webhookDeliveries' => fn ($query) => $query->latest('id')]);

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

        $demo = (bool) config('invoices.demo.enabled');
        $delivery = null;

        DB::transaction(function () use ($invoice, $demo, &$delivery): void {
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

                if (! $demo) {
                    DeliverWebhook::dispatch($delivery)->afterCommit();
                }
            }
        });

        if ($demo) {
            if ($delivery !== null) {
                $this->runNow(new DeliverWebhook($delivery));
            }

            // Stays on the invoice, the visitor came to see the webhook go out.
            return to_route('invoices.show', $invoice)->with('status', "Approved {$this->label($invoice)}.");
        }

        $next = Invoice::query()
            ->where('status', InvoiceStatus::NeedsReview)
            ->oldest('id')
            ->first();

        $redirect = $next ? to_route('invoices.show', $next) : to_route('invoices.index');

        return $redirect->with('status', "Approved {$this->label($invoice)}.");
    }

    public function retry(Invoice $invoice, ExtractionQuota $quota): RedirectResponse
    {
        abort_unless($invoice->status === InvoiceStatus::Failed, 409, 'Only failed invoices can be retried.');

        $quota->hitShared();

        $invoice->update(['status' => InvoiceStatus::Processing, 'error' => null]);

        $this->read($invoice);

        if (config('invoices.demo.enabled')) {
            return $this->afterReading($invoice);
        }

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
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /**
     * No worker runs on the demo, so the job happens inside this request and
     * the visitor waits a few seconds for the result.
     */
    private function read(Invoice $invoice): void
    {
        if (config('invoices.demo.enabled')) {
            $this->runNow(new ProcessInvoice($invoice));

            return;
        }

        ProcessInvoice::dispatch($invoice);
    }

    /**
     * A job that throws here has already recorded the failure on its row, and
     * the next page shows it. That is no reason to answer with a 500.
     */
    private function runNow(object $job): void
    {
        try {
            dispatch_sync($job);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function afterReading(Invoice $invoice): RedirectResponse
    {
        $redirect = to_route('invoices.show', $invoice);

        return $invoice->refresh()->status === InvoiceStatus::Failed
            ? $redirect
            : $redirect->with('status', 'Read just now. Check the fields next to the original, then approve.');
    }

    /**
     * A demo job lives and dies with its request. If PHP went down mid-read
     * nothing will ever finish the row, and the page would refresh forever.
     */
    private function failStalled(): void
    {
        if (! config('invoices.demo.enabled')) {
            return;
        }

        Invoice::query()
            ->where('status', InvoiceStatus::Processing)
            ->where('updated_at', '<', now()->subSeconds((int) config('invoices.llm.timeout') + 30))
            ->update(['status' => InvoiceStatus::Failed, 'error' => 'Reading stopped before it finished. Try again.']);
    }

    private function label(Invoice $invoice): string
    {
        return $invoice->invoice_number ?? $invoice->original_name;
    }
}
