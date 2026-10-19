<?php

namespace App\Http\Controllers;

use App\Invoices\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function store(CsvExport $export): RedirectResponse
    {
        $result = $export->run();

        if ($result === null) {
            return back()->with('error', 'Nothing to export, there are no approved invoices waiting.');
        }

        return back()
            ->with('status', trans_choice('{1} Exported 1 invoice.|[2,*] Exported :count invoices.', $result['count']))
            ->with('download', basename($result['path']));
    }

    public function show(string $file): StreamedResponse
    {
        $path = CsvExport::DIRECTORY.'/'.$file;

        abort_unless(preg_match('/^invoices-[\d-]+\.csv$/', $file) && Storage::exists($path), 404);

        return Storage::download($path, $file, ['Content-Type' => 'text/csv']);
    }
}
