<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    private const MONEY_FIELDS = ['subtotal', 'tax_base', 'tax_amount', 'total'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $money = ['nullable', 'integer', 'min:0', 'max:999999999999999'];
        // Discount lines are printed as negative amounts.
        $lineMoney = ['nullable', 'integer', 'min:-999999999999999', 'max:999999999999999'];

        return [
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'supplier_npwp' => ['nullable', 'string', 'max:16'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'items' => ['array', 'max:200'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => $lineMoney,
            'items.*.amount' => $lineMoney,
            'subtotal' => $money,
            'tax_base' => $money,
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_amount' => $money,
            'total' => $money,
            'accept_flags' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items.*.description' => 'line item description',
            'items.*.quantity' => 'line item quantity',
            'items.*.unit_price' => 'line item unit price',
            'items.*.amount' => 'line item amount',
            'supplier_npwp' => 'NPWP',
            'tax_base' => 'tax base',
            'tax_rate' => 'tax rate',
            'tax_amount' => 'tax amount',
        ];
    }

    /**
     * Reviewers type amounts the way they read them ("Rp 1.250.000"), so
     * strip the formatting before validating.
     */
    protected function prepareForValidation(): void
    {
        $clean = [];

        foreach (self::MONEY_FIELDS as $field) {
            $clean[$field] = $this->cleanMoney($this->input($field));
        }

        $clean['tax_rate'] = $this->cleanDecimal($this->input('tax_rate'));
        $clean['supplier_npwp'] = $this->filled('supplier_npwp')
            ? preg_replace('/\D/', '', (string) $this->input('supplier_npwp'))
            : null;

        $clean['items'] = collect((array) $this->input('items', []))
            ->filter(fn (mixed $item): bool => is_array($item) && array_filter($item, filled(...)) !== [])
            ->map(fn (array $item): array => [
                'description' => $item['description'] ?? null,
                'quantity' => $this->cleanDecimal($item['quantity'] ?? null),
                'unit_price' => $this->cleanMoney($item['unit_price'] ?? null),
                'amount' => $this->cleanMoney($item['amount'] ?? null),
            ])
            ->all();

        $this->merge($clean);
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceAttributes(): array
    {
        $data = $this->validated();

        return [
            'supplier_name' => $data['supplier_name'] ?? null,
            'supplier_npwp' => $data['supplier_npwp'] ?: null,
            'invoice_number' => $data['invoice_number'] ?? null,
            'invoice_date' => $data['invoice_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'line_items' => array_map(fn (array $item): array => [
                'description' => $item['description'] ?? null,
                'quantity' => $this->toNumber($item['quantity'] ?? null),
                'unit_price' => $this->toInteger($item['unit_price'] ?? null),
                'amount' => $this->toInteger($item['amount'] ?? null),
            ], $data['items'] ?? []),
            'subtotal' => $this->toInteger($data['subtotal'] ?? null),
            'tax_base' => $this->toInteger($data['tax_base'] ?? null),
            'tax_rate' => $data['tax_rate'] ?? null,
            'tax_amount' => $this->toInteger($data['tax_amount'] ?? null),
            'total' => $this->toInteger($data['total'] ?? null),
        ];
    }

    private function cleanMoney(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $value = preg_replace('/^(rp\.?)|[\s.]/i', '', (string) $value);

        // Rupiah sen are never used, drop a trailing ",00".
        return preg_replace('/,\d{1,2}$/', '', (string) $value);
    }

    private function cleanDecimal(mixed $value): ?string
    {
        return blank($value) ? null : str_replace(',', '.', trim((string) $value));
    }

    private function toInteger(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function toNumber(mixed $value): int|float|null
    {
        if ($value === null) {
            return null;
        }

        $number = (float) $value;

        return floor($number) === $number ? (int) $number : $number;
    }
}
