<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Concerns;

use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant-owned:
 *
 * - a global scope filters every query by the active tenant;
 * - on create, `tenant_id` is forced to the active tenant (client-provided
 *   values are ignored inside a tenant request — see docs/TENANCY.md).
 *
 * When no tenant context is set (console, seeders, cross-tenant tests) the
 * scope is inert; tenant-scoped HTTP endpoints must require a resolved context.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $context = app(TenantContext::class);

            if ($context->has()) {
                $builder->where(
                    $builder->getModel()->getTable().'.tenant_id',
                    $context->tenantId(),
                );
            }
        });

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($context->has()) {
                $model->setAttribute('tenant_id', $context->tenantId());
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
