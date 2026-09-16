<?php

declare(strict_types=1);

use App\Modules\Tenancy\Application\MigradorLegacyATenant;
use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\Models\AcuerdoTenant;
use App\Modules\Tenancy\Models\DerechoTenant;
use App\Modules\Tenancy\Models\Estudio;
use App\Modules\Tenancy\Models\MovimientoCreditoTenant;
use App\Modules\Tenancy\Models\OfertaTenant;
use App\Modules\Tenancy\Models\OrganizacionTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\ProductoTenant;
use App\Modules\Tenancy\Models\SucursalTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\Usuario;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

/**
 * Crea un tenant legacy con datos representativos y devuelve el tenant.
 */
function tenantLegacyConDatos(): Tenant
{
    $td = tenantConDueno();               // tenant + owner (rol propietario)
    crearOfertaYSucursal($td['tenant']);  // org → marca → sucursal + programa/actividad/oferta
    participanteConDerecho($td['tenant'], 8000); // persona + producto + acuerdo + derecho + ledger

    return $td['tenant'];
}

it('el dry-run reporta el plan sin crear el estudio ni escribir', function (): void {
    $tenant = tenantLegacyConDatos();

    $reporte = app(MigradorLegacyATenant::class)->migrar($tenant, dryRun: true);

    expect($reporte['dry_run'])->toBeTrue();
    expect($reporte['plan']['usuarios'])->toBe(1);
    expect($reporte['plan']['personas'])->toBe(2); // dueno + participante
    expect($reporte['plan']['ofertas'])->toBe(1);
    expect($reporte['plan']['derechos'])->toBe(1);
    expect($reporte['plan']['movimientos'])->toBeGreaterThan(0);

    // No se creo ningun estudio.
    expect(Estudio::query()->where('tenant_legacy_id', $tenant->id)->exists())->toBeFalse();
});

it('migra el tenant legacy a su base por tenant conservando datos y saldos', function (): void {
    $tenant = tenantLegacyConDatos();

    $reporte = app(MigradorLegacyATenant::class)->migrar($tenant);

    expect($reporte['ok'])->toBeTrue();

    // Se creo y aprovisiono el estudio (cutover a activo).
    $estudio = Estudio::query()->where('tenant_legacy_id', $tenant->id)->firstOrFail();
    expect($estudio->estado->value)->toBe('active');

    // La BD del tenant tiene los datos migrados, con saldo del ledger conservado.
    $c = app(GestorDeConexionTenant::class)->ejecutarEn($estudio, fn (): array => [
        'usuarios' => Usuario::query()->count(),
        'rol' => (string) Usuario::query()->value('rol'),
        'personas' => PersonaTenant::query()->count(),
        'organizaciones' => OrganizacionTenant::query()->count(),
        'sucursales' => SucursalTenant::query()->count(),
        'ofertas' => OfertaTenant::query()->count(),
        'productos' => ProductoTenant::query()->count(),
        'acuerdos' => AcuerdoTenant::query()->count(),
        'derechos' => DerechoTenant::query()->count(),
        'saldo' => (int) MovimientoCreditoTenant::query()->sum('unidades'),
    ]);

    expect($c['usuarios'])->toBe(1);
    expect($c['rol'])->toBe('propietario');
    expect($c['personas'])->toBe(2); // dueno (con user) + participante
    expect($c['organizaciones'])->toBe(1);
    expect($c['sucursales'])->toBe(1);
    expect($c['ofertas'])->toBe(1);
    expect($c['productos'])->toBe(1);
    expect($c['acuerdos'])->toBe(1);
    expect($c['derechos'])->toBe(1);
    expect($c['saldo'])->toBe(8000); // el saldo del pack se conserva
});

it('es idempotente: re-migrar un estudio con datos lo omite', function (): void {
    $tenant = tenantLegacyConDatos();
    $migrador = app(MigradorLegacyATenant::class);

    $migrador->migrar($tenant);
    $segundo = $migrador->migrar($tenant);

    expect($segundo['omitido'] ?? null)->toBe('ya migrado');
    // Sigue habiendo un solo estudio para ese tenant legacy.
    expect(Estudio::query()->where('tenant_legacy_id', $tenant->id)->count())->toBe(1);
});

it('migra tenants aislados: cada estudio recibe solo sus datos', function (): void {
    $a = tenantConDueno();
    participanteConDerecho($a['tenant'], 5000);
    $b = tenantConDueno();
    participanteConDerecho($b['tenant'], 3000);
    participanteConDerecho($b['tenant'], 3000);

    $migrador = app(MigradorLegacyATenant::class);
    $migrador->migrar($a['tenant']);
    $migrador->migrar($b['tenant']);

    $ea = Estudio::query()->where('tenant_legacy_id', $a['tenant']->id)->firstOrFail();
    $eb = Estudio::query()->where('tenant_legacy_id', $b['tenant']->id)->firstOrFail();

    $gestor = app(GestorDeConexionTenant::class);
    $personasA = $gestor->ejecutarEn($ea, fn (): int => PersonaTenant::query()->count());
    $personasB = $gestor->ejecutarEn($eb, fn (): int => PersonaTenant::query()->count());

    expect($personasA)->toBe(2); // dueno + 1 participante
    expect($personasB)->toBe(3); // dueno + 2 participantes
});
