<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * There is no login, so anything a stranger uploaded to the public demo would
 * be visible to every other visitor. Hiding the form isn't enough, a POST
 * doesn't need it.
 */
class NoUploadsOnDemo
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(config('invoices.demo.enabled'), 403, 'Uploads are off in this public demo. They work when you run it yourself.');

        return $next($request);
    }
}
