<?php

namespace App\Extraction;

use App\Support\IndonesianNumber;

/**
 * The model's answer, cleaned up into types the rest of the app can trust.
 * Values are never "fixed" here, only parsed, so the checks still see what
 * was actually printed on the invoice.
 */
final readonly class ExtractedInvoice
{
    /**
     * @param  list<array{description: ?string, quantity: int|float|null, unit_price: ?int, amount: ?int}>  $lineItems
     */
    public function __construct(
        public ?string $supplierName,
        public ?string $supplierNpwp,
        public ?string $invoiceNumber,
        public ?string $invoiceDate,
        public ?string $dueDate,
        public string $currency,
        public array $lineItems,
        public ?int $subtotal,
        public ?int $taxBase,
        public ?float $taxRate,
        public ?int $taxAmount,
        public ?int $total,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $items = is_array($data['line_items'] ?? null) ? $data['line_items'] : [];

        return new self(
            supplierName: self::text($data['supplier_name'] ?? null),
            supplierNpwp: self::npwp($data['supplier_npwp'] ?? null),
            invoiceNumber: self::text($data['invoice_number'] ?? null),
            invoiceDate: self::date($data['invoice_date'] ?? null),
            dueDate: self::date($data['due_date'] ?? null),
            currency: strtoupper(self::text($data['currency'] ?? null) ?? 'IDR'),
            lineItems: array_values(array_map(fn (mixed $item): array => [
                'description' => self::text($item['description'] ?? null),
                'quantity' => self::quantity($item['quantity'] ?? null),
                'unit_price' => self::money($item['unit_price'] ?? null),
                'amount' => self::money($item['amount'] ?? null),
            ], array_filter($items, is_array(...)))),
            subtotal: self::money($data['subtotal'] ?? null),
            taxBase: self::money($data['tax_base'] ?? null),
            taxRate: IndonesianNumber::parse($data['tax_rate'] ?? null),
            taxAmount: self::money($data['tax_amount'] ?? null),
            total: self::money($data['total'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'supplier_name' => $this->supplierName,
            'supplier_npwp' => $this->supplierNpwp,
            'invoice_number' => $this->invoiceNumber,
            'invoice_date' => $this->invoiceDate,
            'due_date' => $this->dueDate,
            'currency' => $this->currency,
            'line_items' => $this->lineItems,
            'subtotal' => $this->subtotal,
            'tax_base' => $this->taxBase,
            'tax_rate' => $this->taxRate,
            'tax_amount' => $this->taxAmount,
            'total' => $this->total,
        ];
    }

    private static function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function npwp(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) self::text($value));

        return $digits === '' ? null : $digits;
    }

    private static function date(mixed $value): ?string
    {
        $value = self::text($value);

        if ($value === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
    }

    private static function money(mixed $value): ?int
    {
        $number = IndonesianNumber::parse($value);

        return $number === null ? null : (int) round($number);
    }

    private static function quantity(mixed $value): int|float|null
    {
        $number = IndonesianNumber::parse($value);

        if ($number === null) {
            return null;
        }

        return floor($number) === $number ? (int) $number : $number;
    }
}
