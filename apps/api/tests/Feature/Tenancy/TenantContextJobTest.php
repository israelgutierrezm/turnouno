<?php

declare(strict_types=1);

use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A throwaway job that records the tenant id visible while it runs.
 */
class RecordsTenantContextJob implements ShouldQueue
{
    use Queueable;

    public static ?int $seenTenantId = null;

    public function handle(TenantContext $context): void
    {
        self::$seenTenantId = $context->tenantId();
    }
}

it('propaga el contexto de tenant a traves de la cola', function (): void {
    config()->set('queue.default', 'database');

    $tenant = crearTenant('Tenant Encolado');
    RecordsTenantContextJob::$seenTenantId = null;

    // Dispatched while a tenant is active: the payload captures the tenant id.
    app(TenantContext::class)->set($tenant);
    dispatch(new RecordsTenantContextJob);

    // The worker starts with no ambient tenant context.
    app(TenantContext::class)->clear();
    expect(app(TenantContext::class)->has())->toBeFalse();

    $this->artisan('queue:work', ['--once' => true]);

    expect(RecordsTenantContextJob::$seenTenantId)->toBe($tenant->id);
});
