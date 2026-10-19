<?php

namespace App\Console\Commands;

use Dompdf\Dompdf;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Renders the fictional invoices in samples/. Two of them carry deliberate
 * mistakes and one is a resend, so every check has something to catch.
 */
class GenerateSampleInvoices extends Command
{
    protected $signature = 'samples:generate {--path=samples : Where to write the PDFs}';

    protected $description = 'Render the fictional demo invoices as PDFs';

    public function handle(): int
    {
        if (! class_exists(Dompdf::class)) {
            $this->error('dompdf is a dev dependency. Run composer install without --no-dev first.');

            return self::FAILURE;
        }

        $dir = base_path($this->option('path'));
        File::ensureDirectoryExists($dir);

        foreach ($this->invoices() as $file => $invoice) {
            $dompdf = new Dompdf(['defaultFont' => 'Helvetica']);
            $dompdf->loadHtml(view('samples.invoice', $this->prepare($invoice))->render());
            $dompdf->setPaper('A4');
            $dompdf->render();

            File::put("{$dir}/{$file}", (string) $dompdf->output());
            $this->line("wrote {$this->option('path')}/{$file}");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed>
     */
    private function prepare(array $invoice): array
    {
        $items = array_map(fn (array $item): array => $item + [
            'amount' => $item['qty'] * $item['price'],
        ], $invoice['items']);

        // Mistakes are applied after the honest numbers are worked out, the
        // way a typo on a real invoice would be.
        foreach ($invoice['typos']['items'] ?? [] as $i => $amount) {
            $items[$i]['amount'] = $amount;
        }

        $subtotal = array_sum(array_column($items, 'amount'));
        $taxBase = $invoice['dpp_nilai_lain'] ? (int) round($subtotal * 11 / 12) : null;
        $tax = (int) round(($taxBase ?? $subtotal) * $invoice['tax_rate'] / 100);
        $tax = $invoice['typos']['tax'] ?? $tax;

        return [
            ...$invoice,
            'items' => $items,
            'subtotal' => $subtotal,
            'taxBase' => $taxBase,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'date' => Carbon::parse($invoice['date'])->locale('id'),
            'due' => Carbon::parse($invoice['date'])->addDays($invoice['terms'])->locale('id'),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function invoices(): array
    {
        $sinarKemas = [
            'supplier' => 'PT Sinar Kemas Abadi',
            'address' => ['Jl. Industri Raya Blok C3 No. 18', 'Kawasan MM2100, Cikarang Barat, Bekasi 17520'],
            'phone' => '(021) 8998 1234',
            'npwp' => '0123 4567 8901 2000',
            'customer' => ['CV Dapur Salatiga', 'Jl. Diponegoro No. 52, Salatiga 50711'],
            'number' => 'SKA/INV/2026/10/0147',
            'date' => '2026-10-02',
            'terms' => 30,
            'po' => 'PO-DS-2609-031',
            'tax_rate' => 12,
            'dpp_nilai_lain' => true,
            'bank' => 'BCA KCP Cikarang, a.n. PT Sinar Kemas Abadi, 523-019-8841',
            'items' => [
                ['name' => 'Kardus single wall 40x30x30 cm', 'qty' => 800, 'unit' => 'pcs', 'price' => 4_650],
                ['name' => 'Lakban coklat 48 mm x 100 yard', 'qty' => 60, 'unit' => 'roll', 'price' => 14_500],
                ['name' => 'Bubble wrap 125 cm x 50 m', 'qty' => 6, 'unit' => 'roll', 'price' => 187_000],
                ['name' => 'Stretch film 50 cm x 300 m', 'qty' => 12, 'unit' => 'roll', 'price' => 96_000],
            ],
        ];

        return [
            '01-sinar-kemas-abadi.pdf' => $sinarKemas,

            '02-mitra-teknik-mandiri.pdf' => [
                'supplier' => 'CV Mitra Teknik Mandiri',
                'address' => ['Jl. Majapahit No. 211', 'Pedurungan, Semarang 50192'],
                'phone' => '(024) 6710 455',
                'npwp' => '02.718.334.6-511.000',
                'customer' => ['CV Dapur Salatiga', 'Jl. Diponegoro No. 52, Salatiga 50711'],
                'number' => 'MTM-2610-0088',
                'date' => '2026-10-06',
                'terms' => 14,
                'po' => null,
                'tax_rate' => 11,
                'dpp_nilai_lain' => false,
                'bank' => 'Bank Mandiri Semarang Pandanaran, a.n. CV Mitra Teknik Mandiri, 135-00-2219876-4',
                'items' => [
                    ['name' => 'Service berkala chiller 2 PK', 'qty' => 2, 'unit' => 'unit', 'price' => 650_000],
                    ['name' => 'Freon R404A 10.9 kg', 'qty' => 3, 'unit' => 'tabung', 'price' => 1_450_000],
                    ['name' => 'Kapasitor 45 uF', 'qty' => 4, 'unit' => 'pcs', 'price' => 85_000],
                    ['name' => 'Biaya transport teknisi', 'qty' => 1, 'unit' => 'ls', 'price' => 250_000],
                ],
                // 3 x 1.450.000 is 4.350.000, the invoice says 4.530.000.
                'typos' => ['items' => [1 => 4_530_000]],
            ],

            '03-higienis-prima.pdf' => [
                'supplier' => 'PT Higienis Prima Sejahtera',
                'address' => ['Jl. Slamet Riyadi No. 377', 'Laweyan, Surakarta 57141'],
                'phone' => '0812 2655 9031',
                'npwp' => '0981 2233 4455 6000',
                'customer' => ['CV Dapur Salatiga', 'Jl. Diponegoro No. 52, Salatiga 50711'],
                'number' => 'INV/HPS/X/2026/0412',
                'date' => '2026-10-09',
                'terms' => 21,
                'po' => 'PO-DS-2610-004',
                'tax_rate' => 11,
                'dpp_nilai_lain' => false,
                'bank' => 'BRI KC Solo Slamet Riyadi, a.n. PT Higienis Prima Sejahtera, 0072-01-001845-30-2',
                'items' => [
                    ['name' => 'Sabun cuci piring jerigen 5 L', 'qty' => 12, 'unit' => 'jrg', 'price' => 68_500],
                    ['name' => 'Cairan desinfektan lantai 4 L', 'qty' => 10, 'unit' => 'jrg', 'price' => 92_000],
                    ['name' => 'Tisu dapur roll isi 2', 'qty' => 48, 'unit' => 'pak', 'price' => 21_500],
                    ['name' => 'Sarung tangan nitril (100)', 'qty' => 15, 'unit' => 'box', 'price' => 78_000],
                    ['name' => 'Plastik sampah 90x120 (isi 10)', 'qty' => 40, 'unit' => 'pak', 'price' => 16_500],
                ],
                // 11% of 4.604.000 is 506.440.
                'typos' => ['tax' => 504_640],
            ],

            // Same invoice sent again a week later, marked as a copy.
            '04-sinar-kemas-abadi-resend.pdf' => [...$sinarKemas, 'copy' => true],
        ];
    }
}
