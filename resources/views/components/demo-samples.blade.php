@if (config('invoices.demo.enabled'))
    <section class="mb-5 rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
        <h2 class="font-medium text-slate-900">Try a sample invoice</h2>
        <p class="text-sm text-slate-500">One click sends a made-up invoice to the model. It takes a few seconds, then opens for review.</p>

        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            @foreach (config('invoices.demo.samples') as $file => $sample)
                <form method="POST" action="{{ route('demo.samples') }}" data-reading>
                    @csrf
                    <input type="hidden" name="sample" value="{{ $file }}">
                    <button type="submit" class="size-full rounded-md border border-slate-300 bg-white px-3 py-2 text-left text-sm hover:border-slate-500 hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60">
                        <span class="block font-medium text-slate-900">{{ $sample['title'] }}</span>
                        <span class="block text-xs text-slate-500">{{ $sample['hint'] }}</span>
                    </button>
                </form>
            @endforeach
        </div>

        @error('sample')
            <p class="mt-2 text-sm text-rose-700">{{ $message }}</p>
        @enderror
    </section>
@endif
