<?php

namespace App\Http\Middleware;

use App\Demo\ExtractionQuota;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitExtractions
{
    public function __construct(private ExtractionQuota $quota) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->quota->hitClient($request);

        return $next($request);
    }
}
