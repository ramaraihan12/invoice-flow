@php
    use App\Enums\InvoiceStatus;

    $tabs = [InvoiceStatus::Processing, InvoiceStatus::NeedsReview, InvoiceStatus::Approved, InvoiceStatus::Exported];

    if (($counts[InvoiceStatus::Failed->value] ?? 0) > 0) {
        $tabs[] = InvoiceStatus::Failed;
    }

    $approved = $counts[InvoiceStatus::Approved->value] ?? 0;
    $processing = $counts[InvoiceStatus::Processing->value] ?? 0;
@endphp

<x-layout title="Invoices" :refresh="$processing > 0 ? 5 : null">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Invoices</h1>
            <p class="text-sm text-slate-500">Uploads are read in the background. Anything that doesn't add up is flagged for review.</p>
        </div>
        <div class="flex items-center gap-2">
            <form method="POST" action="{{ route('exports.store') }}">
                @csrf
                <button type="submit" @disabled($approved === 0)
                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-xs hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                    Export approved to CSV ({{ $approved }})
                </button>
            </form>
            <a href="{{ route('invoices.create') }}" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white shadow-xs hover:bg-slate-700">Upload invoices</a>
        </div>
    </div>

    <div @class(['mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4', 'lg:grid-cols-5' => count($tabs) === 5])>
        @foreach ($tabs as $tab)
            @php $active = $status === $tab; @endphp
            <a href="{{ route('invoices.index', $active ? [] : ['status' => $tab->value]) }}" @class([
                'rounded-lg border bg-white px-4 py-3 shadow-xs transition',
                'border-slate-900 ring-1 ring-slate-900' => $active,
                'border-slate-200 hover:border-slate-300' => ! $active,
            ])>
                <div class="text-2xl font-semibold tabular-nums text-slate-900">{{ $counts[$tab->value] ?? 0 }}</div>
                <div class="mt-0.5"><x-status-badge :status="$tab" /></div>
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xs">
        @if ($invoices->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="font-medium text-slate-700">{{ $status ? 'Nothing here.' : 'No invoices yet.' }}</p>
                <p class="mt-1 text-sm text-slate-500">
                    @if ($status)
                        <a href="{{ route('invoices.index') }}" class="underline">Show all invoices</a>
                    @else
                        <a href="{{ route('invoices.create') }}" class="underline">Upload a PDF</a> to get started. There are a few fictional ones in <code class="text-xs">samples/</code>.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Supplier</th>
                            <th class="px-4 py-2.5">Invoice no.</th>
                            <th class="px-4 py-2.5">Date</th>
                            <th class="px-4 py-2.5">Due</th>
                            <th class="px-4 py-2.5 text-right">Total</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5 text-right">Uploaded</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($invoices as $invoice)
                            @php
                                $flagCount = count($invoice->flags ?? []);
                                $overdue = $invoice->due_date?->isPast() && in_array($invoice->status, [InvoiceStatus::NeedsReview, InvoiceStatus::Approved], true);
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="font-medium text-slate-900 hover:underline">
                                        {{ $invoice->supplier_name ?? $invoice->original_name }}
                                    </a>
                                    @if ($invoice->supplier_name)
                                        <div class="text-xs text-slate-400">{{ $invoice->original_name }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $invoice->invoice_number ?? '-' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ $invoice->invoice_date?->format('d M Y') ?? '-' }}</td>
                                <td @class([
                                    'px-4 py-3 whitespace-nowrap',
                                    'font-medium text-rose-600' => $overdue,
                                    'text-slate-600' => ! $overdue,
                                ]) @if ($overdue) title="Past due" @endif>{{ $invoice->due_date?->format('d M Y') ?? '-' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums text-slate-900">{{ rupiah($invoice->total) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <x-status-badge :status="$invoice->status" />
                                    @if ($invoice->status === InvoiceStatus::NeedsReview && $flagCount > 0)
                                        <span class="ml-1 text-xs text-amber-700">{{ $flagCount }} {{ Str::plural('flag', $flagCount) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap text-xs text-slate-400" title="{{ $invoice->created_at }}">{{ $invoice->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-4">
        {{ $invoices->links() }}
    </div>
</x-layout>
