<?php

namespace App\Http\Controllers;

use App\Models\DemoWebhookReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The demo's fixed webhook target, so visitors can see a signed delivery arrive.
 * It does what the README asks of a receiver. Anything it can't verify is
 * turned away and not stored, so strangers can't write to it.
 */
class DemoWebhookController extends Controller
{
    private const MAX_AGE_SECONDS = 300;

    public function index(): View
    {
        return view('demo.webhook', [
            'receipts' => DemoWebhookReceipt::query()->latest('id')->limit(20)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $body = $request->getContent();
        $secret = (string) config('invoices.webhook.secret');
        $timestamp = (string) $request->header('X-Invoice-Flow-Timestamp');
        $signature = (string) $request->header('X-Invoice-Flow-Signature');
        $expected = 'sha256='.hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        if ($secret === '' || ! hash_equals($expected, $signature) || abs(now()->getTimestamp() - (int) $timestamp) > self::MAX_AGE_SECONDS) {
            return response()->json(['error' => 'Bad signature or old timestamp.'], 401);
        }

        // The receipts page is public, so someone could send one back. Once is enough.
        DemoWebhookReceipt::query()->firstOrCreate(
            ['delivery_uuid' => (string) $request->header('X-Invoice-Flow-Delivery')],
            [
                'event' => (string) $request->header('X-Invoice-Flow-Event'),
                'signature' => $signature,
                'signed_at' => (int) $timestamp,
                'body' => $body,
            ],
        );

        return response()->json(['received' => true]);
    }
}
