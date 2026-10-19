<?php

use App\Extraction\InvoiceDocument;
use App\Extraction\InvoiceExtractor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * These go through Prism's real Ollama provider with only the HTTP layer
 * faked, to pin down the exact request the app sends.
 */
function fakeOllamaChat(): void
{
    Http::fake([
        '*/api/chat' => Http::response([
            'model' => 'qwen3.5:9b',
            'message' => ['role' => 'assistant', 'content' => json_encode(sampleExtraction())],
            'done' => true,
            'done_reason' => 'stop',
            'prompt_eval_count' => 900,
            'eval_count' => 400,
        ]),
    ]);
}

beforeEach(function () {
    config([
        'invoices.llm.provider' => 'ollama',
        'invoices.llm.model' => 'qwen3.5:9b',
        'prism.providers.ollama.url' => 'http://ollama.test:11434',
    ]);
});

it('asks ollama for json matching the schema with thinking off', function () {
    config(['invoices.ollama_think' => false]);
    fakeOllamaChat();

    $result = app(InvoiceExtractor::class)->extract(InvoiceDocument::fromText('No. Invoice : SKA/INV/2026/10/0147'));

    expect($result->invoice->total)->toBe(7619040)
        ->and($result->model)->toBe('ollama:qwen3.5:9b');

    Http::assertSent(function (Request $request) {
        // The flag is added by middleware, so it only shows up in the final body.
        $body = json_decode($request->body(), true);

        return $request->url() === 'http://ollama.test:11434/api/chat'
            && $body['think'] === false
            && $body['format']['type'] === 'object'
            && in_array('invoice_number', $body['format']['required'], true)
            && $body['options']['num_ctx'] === 8192
            && $body['options']['temperature'] === 0;
    });
});

it('leaves think alone when set to null', function () {
    config(['invoices.ollama_think' => null]);
    fakeOllamaChat();

    app(InvoiceExtractor::class)->extract(InvoiceDocument::fromText('invoice text'));

    Http::assertSent(fn (Request $request) => ! array_key_exists('think', json_decode($request->body(), true)));
});
