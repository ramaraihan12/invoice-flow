@props(['title' => null, 'refresh' => null])

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($refresh)
        <meta http-equiv="refresh" content="{{ $refresh }}">
    @endif
    <title>{{ $title ? "{$title} · " : '' }}{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6">
            <a href="{{ route('invoices.index') }}" class="flex items-center gap-2 font-semibold text-slate-900">
                <span class="grid size-7 place-items-center rounded-md bg-slate-900 text-xs font-bold text-white">IF</span>
                Invoice Flow
            </a>
            <nav class="flex items-center gap-1 text-sm">
                <a href="{{ route('invoices.index') }}" @class([
                    'rounded-md px-3 py-1.5',
                    'bg-slate-100 font-medium text-slate-900' => request()->routeIs('invoices.index', 'invoices.show'),
                    'text-slate-600 hover:text-slate-900' => ! request()->routeIs('invoices.index', 'invoices.show'),
                ])>Invoices</a>
                <a href="{{ route('invoices.create') }}" @class([
                    'rounded-md px-3 py-1.5',
                    'bg-slate-100 font-medium text-slate-900' => request()->routeIs('invoices.create'),
                    'text-slate-600 hover:text-slate-900' => ! request()->routeIs('invoices.create'),
                ])>Upload</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
        @if (session('status'))
            <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">
                {{ session('status') }}
                @if (session('download'))
                    <a href="{{ route('exports.show', session('download')) }}" class="ml-1 font-medium underline">Download {{ session('download') }}</a>
                @endif
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
