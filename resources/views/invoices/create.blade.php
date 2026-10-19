<x-layout title="Upload">
    <div class="mx-auto max-w-2xl">
        <h1 class="text-xl font-semibold text-slate-900">Upload invoices</h1>
        <p class="mt-1 text-sm text-slate-500">
            Up to 20 files at once, 10 MB each. Each one is read by the model in the background and lands in the review queue.
        </p>

        <form method="POST" action="{{ route('invoices.store') }}" enctype="multipart/form-data" class="mt-6" data-upload>
            @csrf

            <label for="files" class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-300 bg-white px-6 py-12 text-center transition hover:border-slate-400" data-dropzone>
                <svg class="size-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <span class="mt-3 text-sm font-medium text-slate-700">Drop files here or click to choose</span>
                <span class="mt-1 text-xs text-slate-500">
                    PDF{{ $acceptsPhotos ? ', JPG, PNG or WebP' : '' }}
                </span>
                <input id="files" type="file" name="files[]" multiple class="sr-only"
                    accept="{{ $acceptsPhotos ? '.pdf,.jpg,.jpeg,.png,.webp' : '.pdf' }}">
            </label>

            <ul class="mt-3 space-y-1 text-sm text-slate-600" data-file-list></ul>

            @if ($errors->any())
                <div class="mt-3 rounded-md border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-800">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @unless ($acceptsPhotos)
                <p class="mt-3 text-xs text-slate-500">
                    Photos are off because the configured model can't read images. Point <code>INVOICE_LLM_MODEL</code> at a vision model and set <code>INVOICE_LLM_VISION=true</code> to turn them on.
                </p>
            @endunless

            <div class="mt-6 flex justify-end">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-xs hover:bg-slate-700">Upload</button>
            </div>
        </form>
    </div>
</x-layout>
