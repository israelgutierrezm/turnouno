<?php

declare(strict_types=1);

use App\Modules\Tenancy\Application\MedirAlumnosActivos;
use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\Models\Estudio;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

/**
 * Registra, activa e inicia sesión; devuelve slug y bearer tenant-local.
 *
 * @return array{slug: string, bearer: string}
 */
function estudioConSesion(string $slug, string $email): array
{
    $r = test()->postJson('/api/v1/registro', [
        'nombre' => 'Estudio '.$slug,
        'slug' => $slug,
        'contacto_nombre' => 'Dueño',
        'contacto_email' => $email,
        'acepta_terminos' => true,
    ])->assertCreated();

    $slug = (string) $r->json('data.estudio.slug');
    $token = (string) $r->json('data.activacion.token');

    test()->postJson("/api/v1/app/{$slug}/activar", [
        'email' => $email, 'token' => $token,
        'password' => 'secreto123', 'password_confirmation' => 'secreto123',
    ])->assertCreated();

    $bearer = (string) test()->postJson("/api/v1/app/{$slug}/login", [
        'email' => $email, 'password' => 'secreto123',
    ])->assertOk()->json('data.token');

    return ['slug' => $slug, 'bearer' => $bearer];
}

/**
 * @return array<string, string>
 */
function conBearer(string $bearer): array
{
    return ['Authorization' => "Bearer {$bearer}"];
}

it('el alta de alumno es tenant-local y aislada entre estudios', function (): void {
    $a = estudioConSesion('estudio-a', 'ana@correo.mx');
    $b = estudioConSesion('estudio-b', 'beto@correo.mx');

    $this->postJson("/api/v1/app/{$a['slug']}/miembros", ['nombre' => 'Rosa'], conBearer($a['bearer']))->assertCreated();
    $this->postJson("/api/v1/app/{$a['slug']}/miembros", ['nombre' => 'Luis'], conBearer($a['bearer']))->assertCreated();

    // A ve sus 2 alumnos; B no ve ninguno (bases separadas).
    $this->getJson("/api/v1/app/{$a['slug']}/miembros", conBearer($a['bearer']))->assertOk()->assertJsonCount(2, 'data');
    $this->getJson("/api/v1/app/{$b['slug']}/miembros", conBearer($b['bearer']))->assertOk()->assertJsonCount(0, 'data');
});

it('la medición cuenta alumnos activos DISTINTOS y solo guarda el agregado en el control plane', function (): void {
    $a = estudioConSesion('estudio-a', 'ana@correo.mx');

    foreach (['M1', 'M2', 'M3'] as $nombre) {
        $this->postJson("/api/v1/app/{$a['slug']}/miembros", ['nombre' => $nombre], conBearer($a['bearer']))->assertCreated();
    }
    // No cuentan: no facturable e instructor.
    $this->postJson("/api/v1/app/{$a['slug']}/miembros", ['nombre' => 'Cortesía', 'es_facturable' => false], conBearer($a['bearer']))->assertCreated();
    $this->postJson("/api/v1/app/{$a['slug']}/miembros", ['nombre' => 'Coach', 'tipo' => 'instructor'], conBearer($a['bearer']))->assertCreated();

    $estudio = Estudio::query()->where('slug', $a['slug'])->firstOrFail();
    $medicion = app(MedirAlumnosActivos::class)->ejecutar($estudio, '2026-09');

    expect($medicion->cantidad)->toBe(3);

    // El control plane guarda SOLO el agregado (cantidad + regla), nunca las personas.
    $this->assertDatabaseHas('mediciones_uso', [
        'estudio_id' => $estudio->id, 'periodo' => '2026-09', 'cantidad' => 3, 'regla_version' => 'v1',
    ]);
});

it('la facturación SaaS muestra plan, estado y uso del periodo', function (): void {
    $a = estudioConSesion('estudio-a', 'ana@correo.mx');
    $this->postJson("/api/v1/app/{$a['slug']}/miembros", ['nombre' => 'Rosa'], conBearer($a['bearer']))->assertCreated();

    $this->getJson("/api/v1/app/{$a['slug']}/facturacion", conBearer($a['bearer']))
        ->assertOk()
        ->assertJsonPath('data.estado_facturacion', 'trial')
        ->assertJsonPath('data.uso.alumnos_activos', 1)
        ->assertJsonPath('data.uso.regla', 'v1');
});

it('una medición congelada no se recalcula (no cambia una factura emitida)', function (): void {
    $a = estudioConSesion('estudio-a', 'ana@correo.mx');
    $this->postJson("/api/v1/app/{$a['slug']}/miembros", ['nombre' => 'Rosa'], conBearer($a['bearer']))->assertCreated();

    $estudio = Estudio::query()->where('slug', $a['slug'])->firstOrFail();
    $medir = app(MedirAlumnosActivos::class);

    $medir->congelar($estudio, '2026-09'); // cantidad 1, congelada

    // Alta de otro alumno tras congelar.
    $this->postJson("/api/v1/app/{$a['slug']}/miembros", ['nombre' => 'Luis'], conBearer($a['bearer']))->assertCreated();

    // Re-medir no cambia el periodo congelado.
    expect($medir->ejecutar($estudio, '2026-09')->cantidad)->toBe(1);
});
