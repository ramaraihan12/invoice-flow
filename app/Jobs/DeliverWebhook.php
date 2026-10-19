<?php

namespace App\Jobs;

use App\Http\Resources\InvoiceResource;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public int $timeout = 30;

    public function __construct(public WebhookDelivery $delivery) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900, 3600];
    }

    public function handle(): void
    {
        $delivery = $this->delivery;
        $body = $this->body();
        $timestamp = (string) now()->getTimestamp();

        $headers = [
            'User-Agent' => 'invoice-flow',
            'X-Invoice-Flow-Event' => $delivery->event,
            'X-Invoice-Flow-Delivery' => $delivery->uuid,
            'X-Invoice-Flow-Timestamp' => $timestamp,
        ];

        $secret = (string) config('invoices.webhook.secret');

        if ($secret !== '') {
            // Signing the timestamp too lets receivers reject old, replayed requests.
            $headers['X-Invoice-Flow-Signature'] = 'sha256='.hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
        }

        $delivery->increment('attempts');

        try {
            $response = Http::timeout(15)
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($delivery->url);
        } catch (ConnectionException $e) {
            $delivery->update(['last_error' => $e->getMessage()]);

            throw $e;
        }

        $delivery->response_status = $response->status();

        if ($response->successful()) {
            $delivery->fill([
                'status' => WebhookDelivery::DELIVERED,
                'last_error' => null,
                'delivered_at' => now(),
            ])->save();

            return;
        }

        $delivery->last_error = "HTTP {$response->status()}: ".Str::limit(trim($response->body()), 300);
        $delivery->save();

        // Other 4xx answers mean the receiver rejected the payload, and sending
        // the same payload again won't change its mind.
        if ($response->clientError() && ! in_array($response->status(), [408, 429], true)) {
            $this->fail(new RuntimeException($delivery->last_error));

            return;
        }

        throw new RuntimeException($delivery->last_error);
    }

    public function failed(?Throwable $e): void
    {
        $this->delivery->update([
            'status' => WebhookDelivery::FAILED,
            'last_error' => $this->delivery->last_error ?? $e?->getMessage(),
        ]);
    }

    private function body(): string
    {
        $invoice = $this->delivery->invoice;

        return (string) json_encode([
            'event' => $this->delivery->event,
            'delivery_id' => $this->delivery->uuid,
            'invoice' => (new InvoiceResource($invoice))->resolve(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
