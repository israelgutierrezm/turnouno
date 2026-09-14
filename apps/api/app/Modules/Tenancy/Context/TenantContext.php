<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Context;

use App\Modules\Tenancy\Models\Tenant;
use RuntimeException;

/**
 * Request-scoped holder for the active tenant.
 *
 * Resolved early in the request lifecycle (see ResolveTenantContext middleware)
 * and never populated from unauthenticated client input as an authority
 * (see docs/TENANCY.md).
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function tenant(): Tenant
    {
        return $this->tenant ?? throw new RuntimeException('Tenant context has not been resolved.');
    }

    public function tenantId(): ?int
    {
        return $this->tenant?->id;
    }

    public function tenantIdOrFail(): int
    {
        return $this->tenant()->id;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
