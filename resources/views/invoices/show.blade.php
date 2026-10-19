@php
    use App\Enums\InvoiceStatus;
    use App\Support\IndonesianNumber;

    $editable = $invoice->isEditable();
    $flags = $invoice->flagList();

    $money = fn (?int $value): string => $value === null ? '' : IndonesianNumber::format($value);
    $plain = fn (mixed $value): string => $value === null || $value === '' ? '' : IndonesianNumber::format((float) $value, grouped: false);

    $items = old('items', array_map(fn (array $item): array => [
        'description' => $item['description'],
        'quantity' => $plain($item['quantity']),
        'unit_price' => $money($item['unit_price']),
        'amount' => $money($item['amount']),
    ], $invoice->line_items ?? []));

    $input = 'w-full rounded-md border px-2 py-1.5 text-sm shadow-xs outline-none focus:ring-2 focus:ring-slate-300 disabled:bg-slate-50 disabled:text-slate-600';
@endphp

<x-layout :title="$invoice->invoice_number ?? $invoice->original_name" :refresh="$invoice->status === InvoiceStatus::Processing ? 5 : null">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('invoices.index') }}" class="text-sm text-slate-500 hover:text-slate-800">&larr; All invoices</a>
            <h1 class="mt-1 flex flex-wrap items-center gap-3 text-xl font-semibold text-slate-900">
                {{ $invoice->invoice_number ?? $invoice->original_name }}
                <x-status-badge :status="$invoice->status" />
            </h1>
            <p class="mt-0.5 text-sm text-slate-500">
                {{ $invoice->original_name }}
                @if ($invoice->extracted_with)
                    · read by <span class="font-mono text-xs">{{ $invoice->extracted_with }}</span> {{ $invoice->extracted_at?->diffForHumans() }}
                @endif
                @if ($invoice->approved_at)
                    · approved {{ $invoice->approved_at->format('d M Y H:i') }}
                @endif
                @if ($invoice->exported_at)
                    · exported {{ $invoice->exported_at->format('d M Y H:i') }}
                @endif
            </p>
        </div>

        @unless (in_array($invoice->status, [InvoiceStatus::Approved, InvoiceStatus::Exported], true))
            <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('Delete this upload?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-md px-3 py-1.5 text-sm text-slate-500 hover:bg-rose-50 hover:text-rose-700">Delete</button>
            </form>
        @endunless
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
        {{-- Original document --}}
        <section class="lg:sticky lg:top-6 lg:self-start">
            <div class="mb-2 flex items-center justify-between text-xs text-slate-500">
                <span class="font-medium uppercase tracking-wide">Original</span>
                <a href="{{ route('invoices.file', $invoice) }}" target="_blank" class="hover:text-slate-800">Open in new tab &nearr;</a>
            </div>
            <div class="h-[70vh] overflow-auto rounded-lg border border-slate-200 bg-slate-200/70 p-3 lg:h-[calc(100vh-11rem)]">
                @if ($invoice->isImage())
                    <img src="{{ route('invoices.file', $invoice) }}" alt="{{ $invoice->original_name }}" class="mx-auto w-full rounded bg-white shadow">
                @else
                    <div class="space-y-3" data-pdf="{{ route('invoices.file', $invoice) }}">
                        <p class="py-10 text-center text-sm text-slate-500" data-pdf-status>Loading PDF...</p>
                    </div>
                @endif
            </div>
        </section>

        {{-- Extracted data --}}
        <section>
            @if ($invoice->status === InvoiceStatus::Processing)
                <div class="rounded-lg border border-sky-200 bg-sky-50 px-5 py-8 text-center">
                    <p class="font-medium text-sky-900">Reading this invoice</p>
                    <p class="mt-1 text-sm text-sky-800">The model is pulling the fields out. This page refreshes on its own.</p>
                </div>
            @elseif ($invoice->status === InvoiceStatus::Failed)
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-5 py-5">
                    <p class="font-medium text-rose-900">Couldn't read this invoice</p>
                    <p class="mt-1 text-sm text-rose-800">{{ $invoice->error }}</p>
                    <form method="POST" action="{{ route('invoices.retry', $invoice) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="rounded-md bg-rose-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-rose-600">Try again</button>
                    </form>
                </div>
            @else
                @if ($editable)
                    @if ($flags->isNotEmpty())
                        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                            <p class="text-sm font-medium text-amber-900">{{ $flags->count() }} {{ Str::plural('thing', $flags->count()) }} to check</p>
                            <ul class="mt-1.5 space-y-1 text-sm text-amber-900">
                                @foreach ($flags as $flag)
                                    <li class="flex gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-amber-500"></span>{{ $flag->message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            All checks passed. Give it a quick look and approve.
                        </div>
                    @endif
                @endif

                <form method="POST" action="{{ route('invoices.approve', $invoice) }}" class="space-y-4">
                    @csrf

                    <fieldset class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                        <legend class="px-1 text-xs font-medium uppercase tracking-wide text-slate-500">Supplier</legend>
                        <div class="grid gap-3 sm:grid-cols-[3fr_2fr]">
                            <x-field name="supplier_name" label="Name" :value="$invoice->supplier_name" :disabled="! $editable" />
                            <x-field name="supplier_npwp" label="NPWP" hint="15 or 16 digits" :value="$invoice->supplier_npwp" :flagged="$invoice->isFlagged('supplier_npwp')" :disabled="! $editable" inputmode="numeric" class="font-mono" />
                        </div>
                    </fieldset>

                    <fieldset class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                        <legend class="px-1 text-xs font-medium uppercase tracking-wide text-slate-500">Invoice</legend>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-field name="invoice_number" label="Number" :value="$invoice->invoice_number" :flagged="$invoice->isFlagged('invoice_number')" :disabled="! $editable" class="font-mono" />
                            <x-field name="invoice_date" label="Date" type="date" :value="$invoice->invoice_date?->toDateString()" :disabled="! $editable" />
                            <x-field name="due_date" label="Due (jatuh tempo)" type="date" :value="$invoice->due_date?->toDateString()" :disabled="! $editable" />
                        </div>
                    </fieldset>

                    <fieldset class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                        <legend class="px-1 text-xs font-medium uppercase tracking-wide text-slate-500">Line items</legend>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[34rem] text-sm">
                                <thead class="text-left text-xs text-slate-500">
                                    <tr>
                                        <th class="pb-1.5 font-medium">Description</th>
                                        <th class="w-20 pb-1.5 font-medium">Qty</th>
                                        <th class="w-32 pb-1.5 font-medium">Unit price</th>
                                        <th class="w-36 pb-1.5 font-medium">Amount</th>
                                        @if ($editable)
                                            <th class="w-8"></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody data-items>
                                    @foreach ($items as $i => $item)
                                        @php $rowFlagged = $invoice->isFlagged("line_items.{$i}") || $invoice->isFlagged('line_items'); @endphp
                                        <tr data-item>
                                            <td class="py-1 pr-2"><input name="items[{{ $i }}][description]" value="{{ $item['description'] }}" @disabled(! $editable) @class([$input, 'border-amber-400 bg-amber-50' => $rowFlagged, 'border-slate-300 bg-white' => ! $rowFlagged])></td>
                                            <td class="py-1 pr-2"><input name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] }}" inputmode="decimal" @disabled(! $editable) @class([$input, 'text-right tabular-nums', 'border-amber-400 bg-amber-50' => $rowFlagged, 'border-slate-300 bg-white' => ! $rowFlagged])></td>
                                            <td class="py-1 pr-2"><input name="items[{{ $i }}][unit_price]" value="{{ $item['unit_price'] }}" inputmode="numeric" @disabled(! $editable) @class([$input, 'text-right tabular-nums', 'border-amber-400 bg-amber-50' => $rowFlagged, 'border-slate-300 bg-white' => ! $rowFlagged])></td>
                                            <td class="py-1 pr-2"><input name="items[{{ $i }}][amount]" value="{{ $item['amount'] }}" inputmode="numeric" @disabled(! $editable) @class([$input, 'text-right tabular-nums', 'border-amber-400 bg-amber-50' => $rowFlagged, 'border-slate-300 bg-white' => ! $rowFlagged])></td>
                                            @if ($editable)
                                                <td class="py-1 text-right"><button type="button" data-remove-item class="rounded px-1.5 text-slate-400 hover:bg-slate-100 hover:text-rose-600" title="Remove line">&times;</button></td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($editable)
                            <template data-item-template>
                                <tr data-item>
                                    <td class="py-1 pr-2"><input name="items[__INDEX__][description]" class="{{ $input }} border-slate-300 bg-white"></td>
                                    <td class="py-1 pr-2"><input name="items[__INDEX__][quantity]" inputmode="decimal" class="{{ $input }} border-slate-300 bg-white text-right tabular-nums"></td>
                                    <td class="py-1 pr-2"><input name="items[__INDEX__][unit_price]" inputmode="numeric" class="{{ $input }} border-slate-300 bg-white text-right tabular-nums"></td>
                                    <td class="py-1 pr-2"><input name="items[__INDEX__][amount]" inputmode="numeric" class="{{ $input }} border-slate-300 bg-white text-right tabular-nums"></td>
                                    <td class="py-1 text-right"><button type="button" data-remove-item class="rounded px-1.5 text-slate-400 hover:bg-slate-100 hover:text-rose-600" title="Remove line">&times;</button></td>
                                </tr>
                            </template>
                            <button type="button" data-add-item class="mt-2 text-sm font-medium text-slate-600 hover:text-slate-900">+ Add line</button>
                        @endif
                    </fieldset>

                    <fieldset class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                        <legend class="px-1 text-xs font-medium uppercase tracking-wide text-slate-500">Totals ({{ $invoice->currency }})</legend>
                        <div class="ml-auto grid max-w-sm gap-3">
                            <x-field name="subtotal" label="Subtotal" :value="$money($invoice->subtotal)" :flagged="$invoice->isFlagged('subtotal')" :disabled="! $editable" inputmode="numeric" class="text-right tabular-nums" />
                            <x-field name="tax_base" label="DPP" hint="only if printed" :value="$money($invoice->tax_base)" :flagged="$invoice->isFlagged('tax_base')" :disabled="! $editable" inputmode="numeric" class="text-right tabular-nums" />
                            <div class="grid grid-cols-[5rem_1fr] gap-3">
                                <x-field name="tax_rate" label="PPN %" :value="$plain($invoice->tax_rate)" :flagged="$invoice->isFlagged('tax_rate')" :disabled="! $editable" inputmode="decimal" class="text-right tabular-nums" />
                                <x-field name="tax_amount" label="PPN" :value="$money($invoice->tax_amount)" :flagged="$invoice->isFlagged('tax_amount')" :disabled="! $editable" inputmode="numeric" class="text-right tabular-nums" />
                            </div>
                            <x-field name="total" label="Total" :value="$money($invoice->total)" :flagged="$invoice->isFlagged('total')" :disabled="! $editable" inputmode="numeric" class="text-right font-semibold tabular-nums" />
                        </div>
                    </fieldset>

                    @if ($editable)
                        <div class="flex flex-wrap items-center justify-end gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-xs">
                            @if ($flags->isNotEmpty())
                                <label class="mr-auto flex items-center gap-2 text-sm text-slate-600">
                                    <input type="checkbox" name="accept_flags" value="1" class="size-4 rounded border-slate-300">
                                    Approve anyway, the supplier really printed it like this
                                </label>
                            @endif
                            {{-- Save comes first so pressing Enter in a field saves instead of approving. --}}
                            <button type="submit" name="_method" value="PUT" formaction="{{ route('invoices.update', $invoice) }}"
                                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Save and re-check</button>
                            <button type="submit" class="rounded-md bg-emerald-700 px-4 py-1.5 text-sm font-medium text-white shadow-xs hover:bg-emerald-600">Approve</button>
                        </div>
                    @endif
                </form>

                @if ($invoice->webhookDeliveries->isNotEmpty())
                    <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                        <h2 class="text-xs font-medium uppercase tracking-wide text-slate-500">Webhook</h2>
                        <ul class="mt-2 divide-y divide-slate-100 text-sm">
                            @foreach ($invoice->webhookDeliveries as $delivery)
                                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                    <span>
                                        <span class="font-mono text-xs">{{ $delivery->event }}</span>
                                        <span class="text-slate-400">to {{ parse_url($delivery->url, PHP_URL_HOST) }}</span>
                                    </span>
                                    <span @class([
                                        'text-xs',
                                        'text-emerald-700' => $delivery->status === \App\Models\WebhookDelivery::DELIVERED,
                                        'text-rose-700' => $delivery->status === \App\Models\WebhookDelivery::FAILED,
                                        'text-slate-500' => $delivery->status === \App\Models\WebhookDelivery::PENDING,
                                    ])>
                                        {{ $delivery->status }}{{ $delivery->response_status ? " (HTTP {$delivery->response_status})" : '' }},
                                        {{ $delivery->attempts }} {{ Str::plural('attempt', $delivery->attempts) }}
                                    </span>
                                    @if ($delivery->last_error && $delivery->status !== \App\Models\WebhookDelivery::DELIVERED)
                                        <span class="w-full text-xs text-rose-700">{{ $delivery->last_error }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($invoice->source_text || $invoice->raw_extraction)
                    <div class="mt-4 space-y-2 text-sm">
                        @if ($invoice->source_text)
                            <details class="rounded-lg border border-slate-200 bg-white px-4 py-2.5">
                                <summary class="cursor-pointer text-slate-600">Text the model read</summary>
                                <pre class="mt-2 max-h-80 overflow-auto text-xs whitespace-pre-wrap text-slate-600">{{ $invoice->source_text }}</pre>
                            </details>
                        @endif
                        @if ($invoice->raw_extraction)
                            <details class="rounded-lg border border-slate-200 bg-white px-4 py-2.5">
                                <summary class="cursor-pointer text-slate-600">Raw model output</summary>
                                <pre class="mt-2 max-h-80 overflow-auto text-xs text-slate-600">{{ json_encode($invoice->raw_extraction, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        @endif
                    </div>
                @endif
            @endif
        </section>
    </div>
</x-layout>
