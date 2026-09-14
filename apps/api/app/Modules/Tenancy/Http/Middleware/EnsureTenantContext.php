<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Context\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards tenant-scoped endpoints: a resolved tenant context is mandatory.
 */
class EnsureTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->has()) {
            return response()->json([
                'code' => 'TENANT_REQUIRED',
                'message' => __('tenant.requerido'),
            ], 400);
        }

        return $next($request);
    }
}
