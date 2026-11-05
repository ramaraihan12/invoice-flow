<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $delivery_uuid
 * @property string $event
 * @property string $signature
 * @property int $signed_at
 * @property string $body
 * @property Carbon $created_at
 */
class DemoWebhookReceipt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['delivery_uuid', 'event', 'signature', 'signed_at', 'body'];

    protected function casts(): array
    {
        return ['signed_at' => 'integer'];
    }

    public function prettyBody(): string
    {
        return (string) json_encode(json_decode($this->body), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
