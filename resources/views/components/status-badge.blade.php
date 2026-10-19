@props(['status'])

@php
    $classes = match ($status) {
        \App\Enums\InvoiceStatus::Processing => 'bg-sky-50 text-sky-700 ring-sky-200',
        \App\Enums\InvoiceStatus::NeedsReview => 'bg-amber-50 text-amber-800 ring-amber-200',
        \App\Enums\InvoiceStatus::Approved => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        \App\Enums\InvoiceStatus::Exported => 'bg-slate-100 text-slate-700 ring-slate-200',
        \App\Enums\InvoiceStatus::Failed => 'bg-rose-50 text-rose-700 ring-rose-200',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', $classes]) }}>
    @if ($status === \App\Enums\InvoiceStatus::Processing)
        <span class="size-1.5 animate-pulse rounded-full bg-sky-500"></span>
    @endif
    {{ $status->label() }}
</span>
