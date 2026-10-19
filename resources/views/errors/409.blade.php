<x-layout title="Already handled">
    <div class="mx-auto max-w-lg py-16 text-center">
        <p class="font-medium text-slate-900">{{ $exception->getMessage() ?: 'This invoice changed in the meantime.' }}</p>
        <p class="mt-1 text-sm text-slate-500">Someone probably got to it first, or the form was sent twice.</p>
        <a href="{{ route('invoices.index') }}" class="mt-4 inline-block text-sm font-medium text-slate-700 underline">Back to invoices</a>
    </div>
</x-layout>
