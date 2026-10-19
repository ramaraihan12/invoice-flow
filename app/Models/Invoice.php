<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Invoices\Flag;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property InvoiceStatus $status
 * @property string $original_name
 * @property string $file_path
 * @property string $mime_type
 * @property ?string $source_text
 * @property ?string $supplier_name
 * @property ?string $supplier_npwp
 * @property ?string $invoice_number
 * @property ?\Illuminate\Support\Carbon $invoice_date
 * @property ?\Illuminate\Support\Carbon $due_date
 * @property string $currency
 * @property ?list<array{description: ?string, quantity: ?float, unit_price: ?int, amount: ?int}> $line_items
 * @property ?int $subtotal
 * @property ?int $tax_base
 * @property ?string $tax_rate
 * @property ?int $tax_amount
 * @property ?int $total
 * @property ?list<array{code: string, message: string, fields: list<string>}> $flags
 * @property ?array<string, mixed> $raw_extraction
 * @property ?string $extracted_with
 * @property ?string $error
 * @property ?\Illuminate\Support\Carbon $extracted_at
 * @property ?\Illuminate\Support\Carbon $approved_at
 * @property ?\Illuminate\Support\Carbon $exported_at
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'status',
        'original_name',
        'file_path',
        'mime_type',
        'source_text',
        'supplier_name',
        'supplier_npwp',
        'invoice_number',
        'invoice_date',
        'due_date',
        'currency',
        'line_items',
        'subtotal',
        'tax_base',
        'tax_rate',
        'tax_amount',
        'total',
        'flags',
        'raw_extraction',
        'extracted_with',
        'error',
        'extracted_at',
        'approved_at',
        'exported_at',
    ];

    protected $attributes = [
        'currency' => 'IDR',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'line_items' => 'array',
            'subtotal' => 'integer',
            'tax_base' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'integer',
            'total' => 'integer',
            'flags' => 'array',
            'raw_extraction' => 'array',
            'extracted_at' => 'datetime',
            'approved_at' => 'datetime',
            'exported_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * @return Collection<int, Flag>
     */
    public function flagList(): Collection
    {
        return collect($this->flags ?? [])->map(fn (array $flag): Flag => Flag::fromArray($flag));
    }

    public function isFlagged(string $field): bool
    {
        return $this->flagList()->contains(fn (Flag $flag): bool => in_array($field, $flag->fields, true));
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isEditable(): bool
    {
        return $this->status === InvoiceStatus::NeedsReview;
    }
}
