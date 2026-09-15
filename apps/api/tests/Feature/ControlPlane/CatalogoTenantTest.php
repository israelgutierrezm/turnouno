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

it('el estudio arma su catálogo (programa→actividad→oferta) en su propia BD', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    $programa = (string) $this->postJson("/api/v1/app/{$e['slug']}/programas", ['nombre' => 'Pole'], conBearer($e['bearer']))
        ->assertCreated()->json('data.id');
    $actividad = (string) $this->postJson("/api/v1/app/{$e['slug']}/programas/{$programa}/actividades", ['nombre' => 'Pole Fitness'], conBearer($e['bearer']))
        ->assertCreated()->json('data.id');
    $this->postJson("/api/v1/app/{$e['slug']}/actividades/{$actividad}/ofertas", [
        'nombre' => 'Clase grupal', 'modalidad' => 'grupal', 'capacidad' => 8,
    ], conBearer($e['bearer']))->assertCreated()->assertJsonPath('data.modalidad', 'grupal');

    $this->getJson("/api/v1/app/{$e['slug']}/programas", conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonPath('data.0.nombre', 'Pole')
        ->assertJsonPath('data.0.actividades.0.nombre', 'Pole Fitness')
        ->assertJsonPath('data.0.actividades.0.ofertas.0.nombre', 'Clase grupal');

    $this->getJson("/api/v1/app/{$e['slug']}/ofertas", conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.actividad', 'Pole Fitness');
});

it('el catálogo es tenant-local: un estudio no ve el de otro', function (): void {
    $a = estudioConSesion('estudio-a', 'a@correo.mx');
    $b = estudioConSesion('estudio-b', 'b@correo.mx');

    $this->postJson("/api/v1/app/{$a['slug']}/programas", ['nombre' => 'Pole'], conBearer($a['bearer']))->assertCreated();

    $this->getJson("/api/v1/app/{$a['slug']}/programas", conBearer($a['bearer']))->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/app/{$b['slug']}/programas", conBearer($b['bearer']))->assertOk()->assertJsonCount(0, 'data');
});

it('un instructor puede ver el catálogo pero no gestionarlo (RBAC tenant-local)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    $this->getJson("/api/v1/app/{$e['slug']}/programas", conBearer($coach))->assertOk();
    $this->postJson("/api/v1/app/{$e['slug']}/programas", ['nombre' => 'Nuevo'], conBearer($coach))->assertStatus(403);
});
