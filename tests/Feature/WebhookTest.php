<?php

use App\Jobs\DeliverWebhook;
use App\Models\Invoice;
use App\Models\WebhookDelivery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function pendingDelivery(): WebhookDelivery
{
    return Invoice::factory()->approved()->create()->webhookDeliveries()->create([
        'uuid' => (string) Str::uuid(),
        'event' => 'invoice.approved',
        'url' => 'https://hooks.example.test/invoices',
        'status' => WebhookDelivery::PENDING,
    ]);
}

it('posts the invoice with an hmac signature over timestamp and body', function () {
    config(['invoices.webhook.secret' => 'shh']);
    $this->freezeTime();
    Http::fake(['hooks.example.test/*' => Http::response('ok')]);
    $delivery = pendingDelivery();

    (new DeliverWebhook($delivery))->handle();

    Http::assertSent(function (Request $request) use ($delivery) {
        $timestamp = $request->header('X-Invoice-Flow-Timestamp')[0];
        $expected = 'sha256='.hash_hmac('sha256', "{$timestamp}.{$request->body()}", 'shh');

        return $request->url() === 'https://hooks.example.test/invoices'
            && $timestamp === (string) now()->getTimestamp()
            && $request->header('X-Invoice-Flow-Signature')[0] === $expected
            && $request->header('X-Invoice-Flow-Delivery')[0] === $delivery->uuid
            && $request['event'] === 'invoice.approved'
            && $request['invoice']['invoice_number'] === $delivery->invoice->invoice_number
            && $request['invoice']['total'] === 3330000
            && $request['invoice']['supplier']['npwp'] === '0123456789012000';
    });

    expect($delivery->refresh()->status)->toBe(WebhookDelivery::DELIVERED)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->response_status)->toBe(200)
        ->and($delivery->delivered_at)->not->toBeNull();
});

it('leaves the signature out when no secret is set', function () {
    config(['invoices.webhook.secret' => null]);
    Http::fake(['*' => Http::response('ok')]);

    (new DeliverWebhook(pendingDelivery()))->handle();

    Http::assertSent(fn (Request $request) => ! $request->hasHeader('X-Invoice-Flow-Signature'));
});

it('throws on a server error so the queue retries later', function () {
    Http::fake(['*' => Http::response('upstream down', 503)]);
    $delivery = pendingDelivery();
    $job = (new DeliverWebhook($delivery))->withFakeQueueInteractions();

    expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'HTTP 503: upstream down');

    expect($delivery->refresh()->status)->toBe(WebhookDelivery::PENDING)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->response_status)->toBe(503);

    $job->assertNotFailed();
});

it('gives up straight away when the receiver rejects the payload', function () {
    Http::fake(['*' => Http::response(['error' => 'unknown supplier'], 422)]);
    $job = (new DeliverWebhook(pendingDelivery()))->withFakeQueueInteractions();

    $job->handle();

    $job->assertFailed();
});

it('retries rate limits like server errors', function () {
    Http::fake(['*' => Http::response('slow down', 429)]);
    $job = (new DeliverWebhook(pendingDelivery()))->withFakeQueueInteractions();

    expect(fn () => $job->handle())->toThrow(RuntimeException::class);

    $job->assertNotFailed();
});

it('records the final failure', function () {
    $delivery = pendingDelivery();
    $delivery->update(['last_error' => 'HTTP 503: upstream down']);

    (new DeliverWebhook($delivery))->failed(new RuntimeException('HTTP 503: upstream down'));

    expect($delivery->refresh()->status)->toBe(WebhookDelivery::FAILED)
        ->and($delivery->last_error)->toBe('HTTP 503: upstream down');
});

it('backs off between attempts', function () {
    $job = new DeliverWebhook(pendingDelivery());

    expect($job->tries)->toBe(6)
        ->and($job->backoff())->toBe([10, 60, 300, 900, 3600]);
});
