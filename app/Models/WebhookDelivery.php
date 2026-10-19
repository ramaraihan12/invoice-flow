<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string $uuid
 * @property string $event
 * @property string $url
 * @property string $status
 * @property int $attempts
 * @property ?int $response_status
 * @property ?string $last_error
 * @property ?\Illuminate\Support\Carbon $delivered_at
 */
class WebhookDelivery extends Model
{
    public const PENDING = 'pending';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'event',
        'url',
        'status',
        'attempts',
        'response_status',
        'last_error',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'response_status' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
