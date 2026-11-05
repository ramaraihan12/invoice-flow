<x-layout title="Limit reached">
    <div class="mx-auto max-w-lg py-16 text-center">
        <p class="font-medium text-slate-900">{{ $exception->getMessage() ?: 'Too many requests.' }}</p>
        <a href="{{ route('invoices.index') }}" class="mt-4 inline-block text-sm font-medium text-slate-700 underline">Back to invoices</a>
    </div>
</x-layout>
