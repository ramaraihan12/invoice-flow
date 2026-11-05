<x-layout title="Webhook receiver">
    <div class="mx-auto max-w-3xl">
        <h1 class="text-xl font-semibold text-slate-900">Webhook receiver</h1>
        <p class="mt-1 text-sm text-slate-500">
            Approving an invoice posts it to this small receiver, which runs inside the demo. It recomputes the HMAC-SHA256 over <code class="text-xs">timestamp.body</code> and compares it with <code class="text-xs">X-Invoice-Flow-Signature</code>. A wrong signature or a timestamp older than 5 minutes gets a 401 and is not kept, so everything below passed that check.
        </p>

        @if ($receipts->isEmpty())
            <div class="mt-6 rounded-lg border border-slate-200 bg-white px-6 py-16 text-center">
                <p class="font-medium text-slate-700">Nothing has arrived yet.</p>
                <p class="mt-1 text-sm text-slate-500"><a href="{{ route('invoices.index') }}" class="underline">Approve an invoice</a> and it shows up here.</p>
            </div>
        @else
            <ul class="mt-6 space-y-4">
                @foreach ($receipts as $receipt)
                    <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-mono text-xs text-slate-900">{{ $receipt->event }}</span>
                            <span class="text-xs text-emerald-700">signature verified, {{ $receipt->created_at->diffForHumans() }}</span>
                        </div>
                        <dl class="mt-2 grid gap-x-3 gap-y-1 text-xs sm:grid-cols-[auto_1fr]">
                            <dt class="text-slate-500">Delivery</dt>
                            <dd class="font-mono break-all text-slate-700">{{ $receipt->delivery_uuid }}</dd>
                            <dt class="text-slate-500">Timestamp</dt>
                            <dd class="font-mono text-slate-700">{{ $receipt->signed_at }}</dd>
                            <dt class="text-slate-500">Signature</dt>
                            <dd class="font-mono break-all text-slate-700">{{ $receipt->signature }}</dd>
                        </dl>
                        <details class="mt-2 text-sm" @if ($loop->first) open @endif>
                            <summary class="cursor-pointer text-slate-600">Payload</summary>
                            <pre class="mt-2 max-h-80 overflow-auto text-xs text-slate-600">{{ $receipt->prettyBody() }}</pre>
                        </details>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layout>
