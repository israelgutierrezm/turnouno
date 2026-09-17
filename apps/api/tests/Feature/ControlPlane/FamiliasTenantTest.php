<?php

declare(strict_types=1);

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use Illuminate\Support\Facades\File;

/*
| Familias (R26): hogar (agrupa a la familia) y tutela (tutor -> dependiente). Modela
| comprador != participante y habilita que un tutor gestione a sus dependientes. Ver
| el roadmap.
*/

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

it('agrupa personas en un hogar', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $mama = crearMiembroTenant($e, 'Mama');
    $hijo = crearMiembroTenant($e, 'Hijo');

    $hogar = (string) $this->postJson("/api/v1/app/{$e['slug']}/hogares", ['nombre' => 'Familia Perez'], conBearer($e['bearer']))
        ->assertCreated()->json('data.id');

    $this->postJson("/api/v1/app/{$e['slug']}/hogares/{$hogar}/personas", ['persona_id' => $mama], conBearer($e['bearer']))->assertOk();
    $r = $this->postJson("/api/v1/app/{$e['slug']}/hogares/{$hogar}/personas", ['persona_id' => $hijo], conBearer($e['bearer']))
        ->assertOk()->json('data');

    expect($r['personas'])->toHaveCount(2);
});

it('registra la tutela y lista los dependientes de un tutor', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $mama = crearMiembroTenant($e, 'Mama');
    $hijo = crearMiembroTenant($e, 'Hijo');

    $this->postJson("/api/v1/app/{$e['slug']}/tutelas", [
        'tutor_id' => $mama, 'dependiente_id' => $hijo, 'parentesco' => 'madre',
    ], conBearer($e['bearer']))->assertCreated();

    $deps = $this->getJson("/api/v1/app/{$e['slug']}/miembros/{$mama}/dependientes", conBearer($e['bearer']))
        ->assertOk()->json('data');

    expect($deps)->toHaveCount(1);
    expect($deps[0]['id'])->toBe($hijo);
    expect($deps[0]['parentesco'])->toBe('madre');
});

it('rechaza que una persona sea su propio tutor', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $persona = crearMiembroTenant($e, 'Ana');

    $this->postJson("/api/v1/app/{$e['slug']}/tutelas", [
        'tutor_id' => $persona, 'dependiente_id' => $persona,
    ], conBearer($e['bearer']))->assertStatus(422);
});

it('gestionar familias exige miembros.gestionar (un instructor no puede)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $mama = crearMiembroTenant($e, 'Mama');
    $hijo = crearMiembroTenant($e, 'Hijo');
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    $this->postJson("/api/v1/app/{$e['slug']}/tutelas", [
        'tutor_id' => $mama, 'dependiente_id' => $hijo,
    ], conBearer($coach))->assertStatus(403);
});
