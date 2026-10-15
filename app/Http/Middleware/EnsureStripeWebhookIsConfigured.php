<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStripeWebhookIsConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        if (blank(config('cashier.secret'))) {
            abort(403);
        }

        if (blank(config('cashier.webhook.secret')) && ! app()->environment('testing')) {
            abort(403);
        }

        return $next($request);
    }
}
