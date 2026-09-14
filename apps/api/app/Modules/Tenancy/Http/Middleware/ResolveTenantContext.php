<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for an authenticated request and installs it into
 * the TenantContext and the spatie team scope.
 *
 * The tenant is taken from the `X-Tenant-ID` (ULID) header, validated against
 * the user's active memberships — never trusted blindly. With no header and a
 * single active membership, that membership is used; otherwise the context is
 * left unresolved (the client must choose a tenant).
 */
class ResolveTenantContext
{
    public const HEADER = 'X-Tenant-ID';

    public function __construct(
        private readonly TenantContext $context,
        private readonly PermissionRegistrar $registrar,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $requested = $request->header(self::HEADER);

        if (is_string($requested) && $requested !== '') {
            $tenant = Tenant::query()->where('ulid', $requested)->first();

            if ($tenant === null || ! $this->isActiveMember($user, $tenant)) {
                return response()->json([
                    'code' => 'TENANT_FORBIDDEN',
                    'message' => __('tenant.no_miembro'),
                ], 403);
            }
        } else {
            $memberships = $user->tenants()->wherePivot('status', 'active')->get();
            $tenant = $memberships->count() === 1 ? $memberships->first() : null;
        }

        if ($tenant instanceof Tenant) {
            $this->context->set($tenant);
            $this->registrar->setPermissionsTeamId($tenant->id);
            Log::shareContext(['tenant_id' => $tenant->id]);
        }

        return $next($request);
    }

    private function isActiveMember(User $user, Tenant $tenant): bool
    {
        return $user->tenants()
            ->where('tenants.id', $tenant->id)
            ->wherePivot('status', 'active')
            ->exists();
    }
}
