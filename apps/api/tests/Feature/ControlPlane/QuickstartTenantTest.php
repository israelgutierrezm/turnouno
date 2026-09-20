<?php

declare(strict_types=1);

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

/**
 * @param  array<int, array{clave: string, hecho: bool}>  $tareas
 */
function tareaHecha(array $tareas, string $clave): bool
{
    foreach ($tareas as $t) {
        if ($t['clave'] === $clave) {
            return $t['hecho'];
        }
    }

    return false;
}

it('un estudio recién creado tiene las tareas requeridas pendientes', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    $data = $this->getJson("/api/v1/app/{$e['slug']}/onboarding/quickstart", conBearer($e['bearer']))
        ->assertOk()->json('data');

    expect($data['listo'])->toBeFalse();
    expect($data['progreso']['hechas'])->toBe(0);
    expect(tareaHecha($data['tareas'], 'sucursal'))->toBeFalse();
    expect(tareaHecha($data['tareas'], 'productos'))->toBeFalse();
});

it('las tareas se marcan hechas al configurar de verdad, y listo pasa a true', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $semilla = agendaSemilla($e);      // sucursal + oferta (catalogo)
    crearSesionTenant($e, $semilla);   // horarios
    crearPackTenant($e, 8000);         // productos

    // Antes de la politica: faltan requeridas -> listo false.
    $data = $this->getJson("/api/v1/app/{$e['slug']}/onboarding/quickstart", conBearer($e['bearer']))->assertOk()->json('data');
    expect(tareaHecha($data['tareas'], 'sucursal'))->toBeTrue();
    expect(tareaHecha($data['tareas'], 'catalogo'))->toBeTrue();
    expect(tareaHecha($data['tareas'], 'horarios'))->toBeTrue();
    expect(tareaHecha($data['tareas'], 'productos'))->toBeTrue();
    expect(tareaHecha($data['tareas'], 'politica'))->toBeFalse();
    expect($data['listo'])->toBeFalse();

    // Define la politica de cancelacion -> se completan todas las requeridas.
    $this->putJson("/api/v1/app/{$e['slug']}/politicas-cancelacion", [
        'horas_limite' => 6, 'penaliza_tarde' => true, 'penaliza_no_show' => true,
    ], conBearer($e['bearer']))->assertSuccessful();

    $data = $this->getJson("/api/v1/app/{$e['slug']}/onboarding/quickstart", conBearer($e['bearer']))->assertOk()->json('data');
    expect(tareaHecha($data['tareas'], 'politica'))->toBeTrue();
    expect($data['listo'])->toBeTrue();
    expect($data['progreso']['hechas'])->toBe($data['progreso']['total']);
});

it('el quickstart exige permiso de gestión del estudio', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    $this->getJson("/api/v1/app/{$e['slug']}/onboarding/quickstart", conBearer($coach))->assertStatus(403);
});
