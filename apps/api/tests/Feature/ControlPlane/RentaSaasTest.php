<?php

declare(strict_types=1);

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

/**
 * @return array<string, string>
 */
function conPlataforma(string $token = 'token-plataforma'): array
{
    return ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];
}

it('el comando genera el cargo de renta y el dueño lo ve en su apartado', function (): void {
    Config::set('turnouno.plataforma.token', 'token-plataforma');
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    // El admin pone cuota fija (monto determinista, sin depender de alumnos activos).
    $this->putJson('/api/v1/plataforma/estudios/estudio-a', [
        'modo_cobro' => 'fijo', 'precio_por_alumno_minor' => 0, 'cuota_fija_minor' => 149900,
    ], conPlataforma())->assertOk();

    $periodo = Carbon::now()->format('Y-m');
    $this->artisan('turnouno:generar-cargos-renta', ['--periodo' => $periodo])->assertSuccessful();

    $this->getJson("/api/v1/app/{$e['slug']}/renta", conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonPath('data.modo_cobro', 'fijo')
        ->assertJsonCount(1, 'data.cargos')
        ->assertJsonPath('data.cargos.0.periodo', $periodo)
        ->assertJsonPath('data.cargos.0.monto_minor', 149900)
        ->assertJsonPath('data.cargos.0.estado', 'pendiente');
});

it('la generación es idempotente: no duplica el cargo del periodo', function (): void {
    Config::set('turnouno.plataforma.token', 'token-plataforma');
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $periodo = Carbon::now()->format('Y-m');

    $this->artisan('turnouno:generar-cargos-renta', ['--periodo' => $periodo])->assertSuccessful();
    $this->artisan('turnouno:generar-cargos-renta', ['--periodo' => $periodo])->assertSuccessful();

    $this->getJson("/api/v1/app/{$e['slug']}/renta", conBearer($e['bearer']))
        ->assertOk()->assertJsonCount(1, 'data.cargos');
});

it('el apartado de renta muestra la estimación del periodo en curso', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    // Sin cargos generados aún: lista vacía pero con la estimación actual (modo activos, 0 alumnos).
    $this->getJson("/api/v1/app/{$e['slug']}/renta", conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonPath('data.modo_cobro', 'activos')
        ->assertJsonCount(0, 'data.cargos')
        ->assertJsonPath('data.actual.cargo_estimado_minor', 0);
});

it('el apartado de renta exige permiso de facturación', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    $this->getJson("/api/v1/app/{$e['slug']}/renta", conBearer($coach))->assertStatus(403);
});
