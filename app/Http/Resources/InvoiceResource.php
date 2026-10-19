<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'supplier' => [
                'name' => $this->supplier_name,
                'npwp' => $this->supplier_npwp,
            ],
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'currency' => $this->currency,
            'line_items' => $this->line_items ?? [],
            'subtotal' => $this->subtotal,
            'tax_base' => $this->tax_base,
            'tax_rate' => $this->tax_rate === null ? null : (float) $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'source_file' => $this->original_name,
        ];
    }
}
