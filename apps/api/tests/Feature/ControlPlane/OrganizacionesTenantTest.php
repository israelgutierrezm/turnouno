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

it('el estudio crea organización y sucursal (con zona horaria) en su propia BD', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    $org = (string) $this->postJson("/api/v1/app/{$e['slug']}/organizaciones", ['nombre' => 'Pole House Studios'], conBearer($e['bearer']))
        ->assertCreated()->json('data.id');

    $this->postJson("/api/v1/app/{$e['slug']}/organizaciones/{$org}/sucursales", [
        'nombre' => 'Roma Norte', 'zona_horaria' => 'America/Mexico_City',
    ], conBearer($e['bearer']))
        ->assertCreated()
        ->assertJsonPath('data.nombre', 'Roma Norte')
        ->assertJsonPath('data.zona_horaria', 'America/Mexico_City');

    $this->getJson("/api/v1/app/{$e['slug']}/organizaciones", conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonPath('data.0.nombre', 'Pole House Studios')
        ->assertJsonPath('data.0.sucursales.0.nombre', 'Roma Norte');

    $this->getJson("/api/v1/app/{$e['slug']}/sucursales", conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.zona_horaria', 'America/Mexico_City');
});

it('las organizaciones/sucursales son tenant-local: un estudio no ve las de otro', function (): void {
    $a = estudioConSesion('estudio-a', 'a@correo.mx');
    $b = estudioConSesion('estudio-b', 'b@correo.mx');

    $this->postJson("/api/v1/app/{$a['slug']}/organizaciones", ['nombre' => 'Org A'], conBearer($a['bearer']))->assertCreated();

    $this->getJson("/api/v1/app/{$a['slug']}/organizaciones", conBearer($a['bearer']))->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/app/{$b['slug']}/organizaciones", conBearer($b['bearer']))->assertOk()->assertJsonCount(0, 'data');
});

it('un instructor puede ver la estructura pero no crearla (RBAC tenant-local)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    // ver sucursales sí; crear organización no.
    $this->getJson("/api/v1/app/{$e['slug']}/sucursales", conBearer($coach))->assertOk();
    $this->postJson("/api/v1/app/{$e['slug']}/organizaciones", ['nombre' => 'X'], conBearer($coach))->assertStatus(403);
});
