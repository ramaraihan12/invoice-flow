<?php

namespace App\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\IndonesianNumber;

/**
 * Checks an invoice for things a reviewer has to look at. It never changes
 * the invoice, it only reports.
 */
class InvoiceChecker
{
    // Suppliers round PPN and line totals differently, one rupiah either way is fine.
    private const TOLERANCE = 1;

    /**
     * Re-run the checks and store the result on the (unsaved) invoice.
     */
    public function refresh(Invoice $invoice): void
    {
        $invoice->flags = array_map(fn (Flag $flag): array => $flag->toArray(), $this->check($invoice));
    }

    /**
     * @return list<Flag>
     */
    public function check(Invoice $invoice): array
    {
        return array_values(array_filter([
            $this->missingNumber($invoice),
            $this->duplicateNumber($invoice),
            $this->npwp($invoice),
            ...$this->lineItems($invoice),
            $this->tax($invoice),
            $this->total($invoice),
        ]));
    }

    private function missingNumber(Invoice $invoice): ?Flag
    {
        if (filled($invoice->invoice_number)) {
            return null;
        }

        return new Flag(FlagCode::MissingInvoiceNumber, 'No invoice number found.', ['invoice_number']);
    }

    private function duplicateNumber(Invoice $invoice): ?Flag
    {
        if (blank($invoice->invoice_number)) {
            return null;
        }

        $query = Invoice::query()
            ->whereKeyNot($invoice->getKey())
            ->where('status', '!=', InvoiceStatus::Failed)
            ->where('invoice_number', $invoice->invoice_number);

        if (filled($invoice->supplier_npwp)) {
            $query->where('supplier_npwp', $invoice->supplier_npwp);
        } elseif (filled($invoice->supplier_name)) {
            $query->whereRaw('lower(supplier_name) = ?', [mb_strtolower($invoice->supplier_name)]);
        } else {
            return null;
        }

        $other = $query->oldest('id')->first();

        if ($other === null) {
            return null;
        }

        return new Flag(
            FlagCode::DuplicateInvoiceNumber,
            "This supplier already sent invoice {$invoice->invoice_number} (upload #{$other->id}).",
            ['invoice_number'],
        );
    }

    private function npwp(Invoice $invoice): ?Flag
    {
        $npwp = $invoice->supplier_npwp;

        if (blank($npwp)) {
            return ($invoice->tax_amount ?? 0) > 0
                ? new Flag(FlagCode::InvalidNpwp, 'Supplier charges PPN but no NPWP was found.', ['supplier_npwp'])
                : null;
        }

        // 15 digits is the old format, 16 is the NIK-based one used since 2024.
        if (! preg_match('/^\d{15,16}$/', $npwp)) {
            return new Flag(FlagCode::InvalidNpwp, 'NPWP should have 15 or 16 digits.', ['supplier_npwp']);
        }

        return null;
    }

    /**
     * @return list<Flag>
     */
    private function lineItems(Invoice $invoice): array
    {
        $items = $invoice->line_items ?? [];

        if ($items === []) {
            return [new Flag(FlagCode::LineItemsMismatch, 'No line items found.', ['line_items'])];
        }

        $flags = [];

        foreach ($items as $i => $item) {
            if ($item['quantity'] === null || $item['unit_price'] === null || $item['amount'] === null) {
                $flags[] = new Flag(FlagCode::LineItemsMismatch, 'Line '.($i + 1).' is missing a quantity, price or amount.', ["line_items.{$i}"]);

                continue;
            }

            $expected = (int) round($item['quantity'] * $item['unit_price']);

            if ($this->differs($expected, $item['amount'])) {
                $flags[] = new Flag(
                    FlagCode::LineItemsMismatch,
                    sprintf('Line %d: %s x %s is %s, but the invoice says %s.', $i + 1, IndonesianNumber::format($item['quantity']), rupiah($item['unit_price']), rupiah($expected), rupiah($item['amount'])),
                    ["line_items.{$i}"],
                );
            }
        }

        $sum = array_sum(array_map(fn (array $item): int => $item['amount'] ?? 0, $items));

        if ($invoice->subtotal === null) {
            $flags[] = new Flag(FlagCode::LineItemsMismatch, 'No subtotal found.', ['subtotal']);
        } elseif ($this->differs($sum, $invoice->subtotal)) {
            $flags[] = new Flag(
                FlagCode::LineItemsMismatch,
                sprintf('Line items add up to %s, but the subtotal says %s.', rupiah($sum), rupiah($invoice->subtotal)),
                ['subtotal'],
            );
        }

        return $flags;
    }

    private function tax(Invoice $invoice): ?Flag
    {
        $rate = $invoice->tax_rate === null ? null : (float) $invoice->tax_rate;
        $amount = $invoice->tax_amount;

        if (! $rate && ! $amount) {
            return null;
        }

        if (! $rate) {
            return new Flag(FlagCode::TaxMismatch, 'PPN amount is filled in but the rate is missing.', ['tax_rate']);
        }

        if ($amount === null) {
            return new Flag(FlagCode::TaxMismatch, 'PPN rate is filled in but the amount is missing.', ['tax_amount']);
        }

        if ($invoice->subtotal === null) {
            return null;
        }

        $base = $invoice->tax_base ?? $invoice->subtotal;

        // Since 2025 most goods carry 12% PPN on "DPP nilai lain", which is 11/12 of the price.
        if ($base !== $invoice->subtotal && $this->differs($base, (int) round($invoice->subtotal * 11 / 12))) {
            return new Flag(
                FlagCode::TaxMismatch,
                sprintf('DPP %s matches neither the subtotal nor 11/12 of it (%s).', rupiah($base), rupiah((int) round($invoice->subtotal * 11 / 12))),
                ['tax_base'],
            );
        }

        $expected = (int) round($base * $rate / 100);

        if ($this->differs($expected, $amount)) {
            return new Flag(
                FlagCode::TaxMismatch,
                sprintf('PPN %s%% of %s is %s, but the invoice says %s.', IndonesianNumber::format($rate), rupiah($base), rupiah($expected), rupiah($amount)),
                ['tax_amount', 'tax_rate'],
            );
        }

        return null;
    }

    private function total(Invoice $invoice): ?Flag
    {
        if ($invoice->total === null) {
            return new Flag(FlagCode::TotalMismatch, 'No total found.', ['total']);
        }

        if ($invoice->subtotal === null) {
            return null;
        }

        $expected = $invoice->subtotal + ($invoice->tax_amount ?? 0);

        if ($this->differs($expected, $invoice->total)) {
            return new Flag(
                FlagCode::TotalMismatch,
                sprintf('Subtotal plus PPN is %s, but the total says %s.', rupiah($expected), rupiah($invoice->total)),
                ['total'],
            );
        }

        return null;
    }

    private function differs(int $a, int $b): bool
    {
        return abs($a - $b) > self::TOLERANCE;
    }
}
