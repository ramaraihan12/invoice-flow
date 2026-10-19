@props(['name', 'label', 'value' => null, 'flagged' => false, 'disabled' => false, 'hint' => null])

<label class="block">
    <span class="mb-1 flex items-center justify-between text-xs font-medium text-slate-500">
        {{ $label }}
        @if ($hint)
            <span class="font-normal text-slate-400">{{ $hint }}</span>
        @endif
    </span>
    <input
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        @disabled($disabled)
        {{ $attributes->class([
            'w-full rounded-md border px-2.5 py-1.5 text-sm shadow-xs outline-none focus:ring-2 focus:ring-slate-300 disabled:bg-slate-50 disabled:text-slate-600',
            'border-rose-400' => $errors->has($name),
            'border-amber-400 bg-amber-50' => $flagged && ! $errors->has($name),
            'border-slate-300 bg-white' => ! $flagged && ! $errors->has($name),
        ])->merge(['type' => 'text']) }}
    >
    @error($name)
        <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
    @enderror
</label>
