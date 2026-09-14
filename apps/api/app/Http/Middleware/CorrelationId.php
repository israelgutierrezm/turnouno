<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures every API request/response carries a correlation id, and that the id
 * is attached to every log record produced while handling the request.
 *
 * An inbound id is accepted only if it is short and safe; otherwise a fresh one
 * is generated (prevents log/header injection via a spoofed header).
 */
class CorrelationId
{
    public const HEADER = 'X-Correlation-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get(self::HEADER);

        $correlationId = is_string($incoming) && preg_match('/^[A-Za-z0-9\-]{1,128}$/', $incoming)
            ? $incoming
            : (string) Str::uuid();

        $request->headers->set(self::HEADER, $correlationId);
        $request->attributes->set('correlation_id', $correlationId);

        Log::shareContext(['correlation_id' => $correlationId]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }
}
