<?php

use App\Enums\InvoiceStatus;
use App\Models\DemoWebhookReceipt;
use App\Models\Invoice;
use App\Models\WebhookDelivery;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;

/**
 * The fake model, returning the printed values of a sample with the wrong line total.
 */
function fakeModel(): mixed
{
    return Prism::fake(array_fill(0, 5, StructuredResponseFake::make()->withStructured(sampleExtraction([
        'line_items' => [
            ['description' => 'Freon R404A 10.9 kg', 'quantity' => 3, 'unit_price' => 1450000, 'amount' => 4530000],
        ],
        'subtotal' => 4530000,
        'tax_base' => null,
        'tax_rate' => 11,
        'tax_amount' => 498300,
        'total' => 5028300,
    ]))));
}

/**
 * @return array<string, mixed>
 */
function geminiReply(): array
{
    return [
        'candidates' => [['content' => ['parts' => [['text' => json_encode(sampleExtraction())]]], 'finishReason' => 'STOP']],
        'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
        'modelVersion' => 'gemini-test',
    ];
}

function signedDelivery(string $body, ?int $timestamp = null, string $secret = 'demo-secret'): array
{
    $timestamp ??= now()->getTimestamp();

    return [
        'X-Invoice-Flow-Event' => 'invoice.approved',
        'X-Invoice-Flow-Delivery' => (string) Str::uuid(),
        'X-Invoice-Flow-Timestamp' => (string) $timestamp,
        'X-Invoice-Flow-Signature' => 'sha256='.hash_hmac('sha256', "{$timestamp}.{$body}", $secret),
    ];
}

describe('with the flag off', function () {
    it('has no demo routes', function () {
        $this->get('/demo/webhook')->assertNotFound();
        $this->post('/demo/webhook')->assertNotFound();
        $this->post('/demo/samples', ['sample' => '01-sinar-kemas-abadi.pdf'])->assertNotFound();
    });

    it('sends / to the invoice list and shows nothing of the demo', function () {
        $this->get('/')->assertRedirect('/invoices');

        $this->get('/invoices')->assertOk()->assertDontSee('Public demo')->assertDontSee('Try a sample invoice');
    });

    it('never counts or limits', function () {
        Storage::fake();
        Queue::fake();

        foreach (range(1, 8) as $i) {
            $this->post('/invoices', ['files' => [samplePdf()]])->assertRedirect('/invoices');
        }

        expect(DB::table('demo_quota_hits')->count())->toBe(0);
    });

    it('keeps the upload form and takes uploads', function () {
        Storage::fake();
        Queue::fake();

        $this->get('/invoices/upload')->assertOk()
            ->assertSee('Up to 20 files at once')
            ->assertSee('type="file"', false)
            ->assertDontSee('Uploads are off');

        $this->get('/invoices')->assertSee('Upload invoices');

        $this->post('/invoices', ['files' => [samplePdf()]])->assertRedirect('/invoices');

        expect(Invoice::count())->toBe(1);
    });

    it('refuses to run the seeder, it would wipe real invoices', function () {
        $invoice = Invoice::factory()->create();

        expect(fn () => $this->seed(DemoSeeder::class))->toThrow(RuntimeException::class, 'DEMO_MODE=true');

        expect($invoice->fresh())->not->toBeNull();
    });
});

describe('with the flag on', function () {
    beforeEach(function () {
        Storage::fake();
        config([
            'invoices.demo.enabled' => true,
            'invoices.llm.vision' => true,
            'invoices.webhook.url' => 'http://127.0.0.1:8080/demo/webhook',
            'invoices.webhook.secret' => 'demo-secret',
        ]);
    });

    it('answers / itself and offers the samples', function () {
        $this->get('/')->assertOk()->assertSee('Public demo')->assertSee('Try a sample invoice')->assertSee('CV Mitra Teknik Mandiri');
    });

    it('reads a sample inside the request and opens it for review', function () {
        $prism = fakeModel();

        $response = $this->post('/demo/samples', ['sample' => '02-mitra-teknik-mandiri.pdf']);

        $invoice = Invoice::sole();

        $response->assertRedirect("/invoices/{$invoice->id}");

        expect($invoice->status)->toBe(InvoiceStatus::NeedsReview)
            ->and($invoice->original_name)->toBe('02-mitra-teknik-mandiri.pdf')
            ->and($invoice->flagList()->pluck('message')->join(' '))->toContain('3 x Rp 1.450.000 is Rp 4.350.000, but the invoice says Rp 4.530.000');

        $prism->assertCallCount(1);
        Storage::assertExists($invoice->file_path);

        $this->get("/invoices/{$invoice->id}")->assertOk()->assertSee('but the invoice says');
    });

    it('turns away a sample name that is not on the list', function (string $name) {
        $prism = fakeModel();

        $this->post('/demo/samples', ['sample' => $name])->assertSessionHasErrors('sample');

        expect(Invoice::count())->toBe(0);
        $prism->assertCallCount(0);
    })->with(['../.env', '/etc/passwd', '01-sinar-kemas-abadi.pdf/../../.env', 'nope.pdf', '']);

    it('shows a model failure on the invoice instead of a 500, without the raw message', function () {
        Prism::shouldReceive('structured')->andThrow(new PrismRateLimitedException([]));

        $this->post('/demo/samples', ['sample' => '01-sinar-kemas-abadi.pdf'])->assertRedirect();

        $invoice = Invoice::sole();

        expect($invoice->status)->toBe(InvoiceStatus::Failed)
            ->and($invoice->error)->toBe('The model could not read this one just now. Try again in a minute.');

        $this->get("/invoices/{$invoice->id}")->assertOk()->assertSee('Try again');
    });

    it('waits out a 503 from the model, since no queue will retry it', function () {
        Sleep::fake();
        config(['invoices.llm.provider' => 'gemini', 'invoices.llm.model' => 'gemini-test']);
        Http::fake(['*' => Http::sequence()
            ->push(['error' => ['code' => 503, 'message' => 'overloaded']], 503)
            ->push(['error' => ['code' => 503, 'message' => 'overloaded']], 503)
            ->push(geminiReply())]);

        $this->post('/demo/samples', ['sample' => '02-mitra-teknik-mandiri.pdf'])->assertRedirect();

        expect(Invoice::sole()->status)->toBe(InvoiceStatus::NeedsReview);
        Http::assertSentCount(3);
    });

    it('gives up on the model when the 503s keep coming', function () {
        Sleep::fake();
        config(['invoices.llm.provider' => 'gemini', 'invoices.llm.model' => 'gemini-test']);
        Http::fake(['*' => Http::response(['error' => ['code' => 503, 'message' => 'overloaded']], 503)]);

        $this->post('/demo/samples', ['sample' => '02-mitra-teknik-mandiri.pdf'])->assertRedirect();

        expect(Invoice::sole()->status)->toBe(InvoiceStatus::Failed);
        Http::assertSentCount(3);
    });

    it('does not spend more of a quota the model says is gone', function () {
        Sleep::fake();
        config(['invoices.llm.provider' => 'gemini', 'invoices.llm.model' => 'gemini-test']);
        Http::fake(['*' => Http::response(['error' => ['code' => 429, 'message' => 'quota']], 429)]);

        $this->post('/demo/samples', ['sample' => '02-mitra-teknik-mandiri.pdf'])->assertRedirect();

        expect(Invoice::sole()->status)->toBe(InvoiceStatus::Failed);
        Http::assertSentCount(1);
    });

    it('reads the image sample with the vision model', function () {
        $prism = fakeModel();

        $this->post('/demo/samples', ['sample' => '05-mitra-teknik-mandiri-foto.png'])->assertRedirect();

        $invoice = Invoice::sole();

        expect($invoice->status)->toBe(InvoiceStatus::NeedsReview)
            ->and($invoice->mime_type)->toBe('image/png')
            ->and($invoice->file_path)->toEndWith('.png')
            ->and($invoice->source_text)->toBeNull();

        Storage::assertExists($invoice->file_path);

        $prism->assertRequest(fn (array $requests) => expect($requests[0]->messages()[0]->images())->toHaveCount(1));
    });

    it('has every sample it offers', function () {
        foreach (array_keys(config('invoices.demo.samples')) as $file) {
            expect(base_path("samples/{$file}"))->toBeFile();
        }
    });

    it('refuses an upload posted straight to the route, and keeps nothing of it', function () {
        $prism = fakeModel();

        $this->post('/invoices', ['files' => [samplePdf()]])
            ->assertForbidden()
            ->assertSee('Uploads are off in this public demo');

        expect(Invoice::count())->toBe(0)
            ->and(Storage::allFiles())->toBe([])
            ->and(DB::table('demo_quota_hits')->count())->toBe(0);

        $prism->assertCallCount(0);
    });

    it('says uploads are off instead of showing the form', function () {
        $this->get('/invoices/upload')->assertOk()
            ->assertSee('Uploads are off in this demo')
            ->assertSee('run it yourself')
            ->assertSee('Try a sample invoice')
            ->assertDontSee('type="file"', false)
            ->assertDontSee('action="'.route('invoices.store').'"', false);

        $this->get('/invoices')->assertOk()->assertDontSee('Upload invoices');
    });

    it('limits each visitor per minute, by the address vercel reports', function () {
        config(['invoices.demo.limits.ip_minute' => 2]);
        fakeModel();

        $visitor = ['x-vercel-forwarded-for' => '203.0.113.7'];
        $click = fn (array $headers) => $this->withHeaders($headers)->post('/demo/samples', ['sample' => '01-sinar-kemas-abadi.pdf']);

        $click($visitor)->assertRedirect();
        $click($visitor)->assertRedirect();

        $click($visitor)->assertStatus(429)->assertHeader('Retry-After')->assertSee('a lot of invoices');

        // Headers a caller can set freely don't make it a different visitor.
        $click([...$visitor, 'cf-connecting-ip' => '198.51.100.1', 'true-client-ip' => '198.51.100.2', 'x-forwarded-for' => '198.51.100.3'])->assertStatus(429);

        $click(['x-vercel-forwarded-for' => '203.0.113.8'])->assertRedirect();

        expect(Invoice::count())->toBe(3);
    });

    it('counts a whole ipv6 /64 as one visitor', function () {
        config(['invoices.demo.limits.ip_minute' => 1]);
        fakeModel();

        $click = fn (string $ip) => $this->withHeaders(['x-vercel-forwarded-for' => $ip])->post('/demo/samples', ['sample' => '01-sinar-kemas-abadi.pdf']);

        $click('2001:db8:1:2::1')->assertRedirect();
        $click('2001:db8:1:2::ffff')->assertStatus(429);
    });

    it('limits each visitor per day', function () {
        config(['invoices.demo.limits.ip_day' => 1]);
        fakeModel();

        $this->post('/demo/samples', ['sample' => '01-sinar-kemas-abadi.pdf'])->assertRedirect();
        $this->post('/demo/samples', ['sample' => '01-sinar-kemas-abadi.pdf'])->assertStatus(429)->assertSee('used up your invoices');
    });

    it('holds a total for all visitors together', function () {
        config(['invoices.demo.limits.all_day' => 1]);
        $prism = fakeModel();

        $click = fn (string $ip) => $this->withHeaders(['x-vercel-forwarded-for' => $ip])->post('/demo/samples', ['sample' => '01-sinar-kemas-abadi.pdf']);

        $click('203.0.113.7')->assertRedirect();
        $click('203.0.113.8')->assertStatus(429)->assertSee('all the invoices it can for today');

        expect(Invoice::count())->toBe(1);
        $prism->assertCallCount(1);
    });

    it('opens the window again once it has passed', function () {
        config(['invoices.demo.limits.all_day' => 1]);
        fakeModel();

        $this->post('/demo/samples', ['sample' => '01-sinar-kemas-abadi.pdf'])->assertRedirect();

        $this->travel(25)->hours();

        $this->post('/demo/samples', ['sample' => '01-sinar-kemas-abadi.pdf'])->assertRedirect();
    });

    it('does not spend the total on what a visitor gets refused for', function () {
        $this->post('/demo/samples', ['sample' => 'nope.pdf'])->assertSessionHasErrors();

        expect(DB::table('demo_quota_hits')->where('bucket', 'all:day')->exists())->toBeFalse();
    });

    it('keeps no plain addresses', function () {
        fakeModel();

        $this->withHeaders(['x-vercel-forwarded-for' => '203.0.113.7'])->post('/demo/samples', ['sample' => '01-sinar-kemas-abadi.pdf']);

        expect(DB::table('demo_quota_hits')->pluck('bucket')->join(' '))->not->toContain('203.0.113.7');
    });

    it('counts a retry like any other read', function () {
        config(['invoices.demo.limits.all_day' => 1]);
        fakeModel();
        Storage::put('invoices/a.pdf', (string) file_get_contents(base_path('samples/01-sinar-kemas-abadi.pdf')));
        $failed = Invoice::factory()->create(['status' => InvoiceStatus::Failed, 'file_path' => 'invoices/a.pdf']);

        $this->post("/invoices/{$failed->id}/retry")->assertRedirect("/invoices/{$failed->id}");
        expect($failed->fresh()->status)->toBe(InvoiceStatus::NeedsReview);

        $failed->refresh()->update(['status' => InvoiceStatus::Failed]);
        $this->post("/invoices/{$failed->id}/retry")->assertStatus(429);
    });

    it('gives up on a read that never finished instead of refreshing forever', function () {
        $stuck = Invoice::factory()->create(['status' => InvoiceStatus::Processing]);
        $reading = Invoice::factory()->create(['status' => InvoiceStatus::Processing]);
        Invoice::query()->whereKey($stuck)->update(['updated_at' => now()->subMinutes(10)]);

        $this->get("/invoices/{$stuck->id}")->assertOk()->assertSee('Reading stopped before it finished');

        expect($stuck->fresh()->status)->toBe(InvoiceStatus::Failed)
            ->and($reading->fresh()->status)->toBe(InvoiceStatus::Processing);
    });

    it('fires the webhook inside the approve request and stays on the invoice', function () {
        Http::fake(['127.0.0.1:8080/*' => Http::response(['received' => true])]);
        $invoice = Invoice::factory()->extracted()->create();

        $this->post("/invoices/{$invoice->id}/approve", reviewForm($invoice))->assertRedirect("/invoices/{$invoice->id}");

        $delivery = WebhookDelivery::sole();

        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Approved)
            ->and($delivery->status)->toBe(WebhookDelivery::DELIVERED);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://127.0.0.1:8080/demo/webhook'
            && $request->hasHeader('X-Invoice-Flow-Signature'));

        $this->get("/invoices/{$invoice->id}")->assertOk()->assertSee('delivered (HTTP 200)')->assertSee('See the signed payload');
    });

    it('stays approved and says so when the receiver is down', function () {
        Http::fake(['*' => Http::response('down', 503)]);
        $invoice = Invoice::factory()->extracted()->create();

        $this->post("/invoices/{$invoice->id}/approve", reviewForm($invoice))->assertRedirect("/invoices/{$invoice->id}");

        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Approved)
            ->and(WebhookDelivery::sole()->status)->toBe(WebhookDelivery::FAILED);
    });

    it('never takes a webhook address from a visitor', function () {
        Http::fake(['*' => Http::response('ok')]);
        $invoice = Invoice::factory()->extracted()->create();

        $form = reviewForm($invoice, [
            'url' => 'http://169.254.169.254/latest/meta-data',
            'webhook_url' => 'http://169.254.169.254/latest/meta-data',
            'INVOICE_WEBHOOK_URL' => 'http://169.254.169.254/latest/meta-data',
        ]);

        $this->post("/invoices/{$invoice->id}/approve", $form);

        expect(WebhookDelivery::sole()->url)->toBe('http://127.0.0.1:8080/demo/webhook');
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'http://127.0.0.1:8080/'));
    });

    it('keeps a delivery with a good signature and shows it', function () {
        $body = '{"event":"invoice.approved","invoice":{"invoice_number":"MTM-2610-0088"}}';

        $this->call('POST', '/demo/webhook', [], [], [], headersToServer(signedDelivery($body)), $body)->assertOk();

        expect(DemoWebhookReceipt::count())->toBe(1);

        $this->get('/demo/webhook')->assertOk()->assertSee('signature verified')->assertSee('MTM-2610-0088');
    });

    it('turns away a delivery it cannot verify', function (Closure $headers) {
        $body = '{"event":"invoice.approved"}';

        $this->call('POST', '/demo/webhook', [], [], [], headersToServer($headers($body)), $body)->assertUnauthorized();

        expect(DemoWebhookReceipt::count())->toBe(0);
    })->with([
        'wrong secret' => [fn (string $body) => signedDelivery($body, secret: 'guess')],
        'old timestamp' => [fn (string $body) => signedDelivery($body, now()->subMinutes(6)->getTimestamp())],
        'no signature' => [fn (string $body) => []],
        'signed for another body' => [fn (string $body) => signedDelivery('{"event":"other"}')],
    ]);

    it('keeps a replayed delivery only once', function () {
        $body = '{"event":"invoice.approved"}';
        $headers = headersToServer(signedDelivery($body));

        $this->call('POST', '/demo/webhook', [], [], [], $headers, $body)->assertOk();
        $this->call('POST', '/demo/webhook', [], [], [], $headers, $body)->assertOk();

        expect(DemoWebhookReceipt::count())->toBe(1);
    });

    it('resets to one invoice waiting for review and leaves the rate limits alone', function () {
        Invoice::factory()->count(3)->create();
        DemoWebhookReceipt::query()->create(['delivery_uuid' => (string) Str::uuid(), 'event' => 'x', 'signature' => 'x', 'signed_at' => 1, 'body' => '{}']);
        DB::table('demo_quota_hits')->insert(['bucket' => 'all:day', 'hits' => 40, 'resets_at' => now()->addDay()->getTimestamp()]);

        $this->seed(DemoSeeder::class);

        $invoice = Invoice::sole();

        expect($invoice->status)->toBe(InvoiceStatus::NeedsReview)
            ->and($invoice->original_name)->toBe('03-higienis-prima.pdf')
            ->and($invoice->flagList()->pluck('message')->join(' '))->toContain('PPN')
            ->and(DemoWebhookReceipt::count())->toBe(0)
            ->and(DB::table('demo_quota_hits')->count())->toBe(1);

        Storage::assertExists($invoice->file_path);
    });

    it('clears out old data by itself when the daily reset stops running', function () {
        $old = Invoice::factory()->create(['created_at' => now()->subDays(3)]);
        $recent = Invoice::factory()->create();
        DB::table('stored_files')->insert(['path' => 'invoices/old.pdf', 'contents' => '', 'size' => 0, 'created_at' => now()->subDays(3)]);
        fakeModel();

        $this->post('/demo/samples', ['sample' => '01-sinar-kemas-abadi.pdf'])->assertRedirect();

        expect($old->fresh())->toBeNull()
            ->and($recent->fresh())->not->toBeNull()
            ->and(DB::table('stored_files')->where('path', 'invoices/old.pdf')->exists())->toBeFalse();
    });
});

/**
 * @param  array<string, string>  $headers
 * @return array<string, string>
 */
function headersToServer(array $headers): array
{
    $server = ['CONTENT_TYPE' => 'application/json'];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return $server;
}
