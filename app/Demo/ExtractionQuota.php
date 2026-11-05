<?php

namespace App\Demo;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the public demo inside what one free Gemini key can take. The counters
 * live in Postgres because containers share no memory, and each count is one
 * upsert, so requests racing each other can't all read a count under the limit.
 */
class ExtractionQuota
{
    private const MINUTE = 60;

    private const DAY = 86_400;

    /**
     * Counted before the request is validated, so junk requests can't keep a
     * container busy either.
     */
    public function hitClient(Request $request): void
    {
        if (! config('invoices.demo.enabled')) {
            return;
        }

        $limits = config('invoices.demo.limits');
        $client = hash_hmac('sha256', $this->clientBucket($request), (string) config('app.key'));

        $this->hit("ip:{$client}:minute", self::MINUTE, $limits['ip_minute'], 'That is a lot of invoices for one minute. Give it a moment and try again.');
        $this->hit("ip:{$client}:day", self::DAY, $limits['ip_day'], 'You have used up your invoices for today. Come back tomorrow, or look around what is already here.');
    }

    /**
     * Counted last, right before the model is called, so a request that was
     * turned away never spends what all visitors share.
     */
    public function hitShared(): void
    {
        if (! config('invoices.demo.enabled')) {
            return;
        }

        $hits = $this->hit('all:day', self::DAY, config('invoices.demo.limits.all_day'), 'The demo has read all the invoices it can for today, it runs on a free model key. Come back tomorrow, or review what is already here.');

        if ($hits === 1) {
            $this->prune();
        }
    }

    private function hit(string $bucket, int $window, int $limit, string $message): int
    {
        $now = now()->getTimestamp();

        $row = DB::selectOne(
            'insert into demo_quota_hits (bucket, hits, resets_at) values (?, 1, ?)
            on conflict (bucket) do update set
                hits = case when demo_quota_hits.resets_at <= ? then 1 else demo_quota_hits.hits + 1 end,
                resets_at = case when demo_quota_hits.resets_at <= ? then excluded.resets_at else demo_quota_hits.resets_at end
            returning hits, resets_at',
            [$bucket, $now + $window, $now, $now],
        );

        if ($row->hits > $limit) {
            abort(429, $message, ['Retry-After' => $row->resets_at - $now]);
        }

        return (int) $row->hits;
    }

    /**
     * Vercel overwrites x-vercel-forwarded-for, so a caller can't forge it.
     * Headers it doesn't know about come through as sent, so nothing else is
     * read here.
     */
    private function clientBucket(Request $request): string
    {
        $ip = trim(explode(',', (string) ($request->header('x-vercel-forwarded-for') ?: $request->ip()))[0]);
        $packed = @inet_pton($ip);

        // One IPv6 customer usually holds a whole /64, so that's the unit to count.
        return $packed !== false && strlen($packed) === 16 ? bin2hex(substr($packed, 0, 8)) : $ip;
    }

    /**
     * The daily reset normally gets there first. This covers the case where it
     * stops running, so old rows can't pile up in the free database.
     */
    private function prune(): void
    {
        $cutoff = now()->subDays(2);

        DB::table('demo_quota_hits')->where('resets_at', '<=', now()->getTimestamp())->delete();
        Invoice::query()->where('created_at', '<', $cutoff)->delete();
        DB::table('stored_files')->where('created_at', '<', $cutoff)->delete();
    }
}
