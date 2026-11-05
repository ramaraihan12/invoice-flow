<x-layout title="File too big">
    <div class="mx-auto max-w-lg py-16 text-center">
        <p class="font-medium text-slate-900">That file is too big.</p>
        <a href="{{ route('invoices.create') }}" class="mt-4 inline-block text-sm font-medium text-slate-700 underline">Back to upload</a>
    </div>
</x-layout>
