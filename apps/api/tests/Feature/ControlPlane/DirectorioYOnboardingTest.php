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

it('el directorio lista por defecto; el estudio puede optar por salirse o marcarse privado; nunca IDs internos', function (): void {
    // Por defecto un estudio operativo aparece en el directorio (sin publicar nada).
    $publico = estudioConSesion('estudio-publico', 'a@correo.mx');
    $privado = estudioConSesion('estudio-privado', 'b@correo.mx');
    $oculto = estudioConSesion('estudio-oculto', 'c@correo.mx');

    // El propietario opta por NO aparecer (solo por URL directa).
    $this->putJson("/api/v1/app/{$oculto['slug']}/publicacion", ['publicado' => false, 'privado' => false], conBearer($oculto['bearer']))
        ->assertOk()->assertJsonPath('data.en_directorio', false);

    // Marcarse privado tambien lo saca del directorio.
    $this->putJson("/api/v1/app/{$privado['slug']}/publicacion", ['publicado' => true, 'privado' => true], conBearer($privado['bearer']))
        ->assertOk()->assertJsonPath('data.en_directorio', false);

    $data = $this->getJson('/api/v1/directorio')->assertOk()->json('data');
    $slugs = collect($data)->pluck('slug');

    expect($slugs)->toContain('estudio-publico'); // aparece por defecto
    expect($slugs)->not->toContain('estudio-privado');
    expect($slugs)->not->toContain('estudio-oculto');
    expect($data[0] ?? [])->not->toHaveKey('id'); // sin IDs internos
});

it('onboarding: guardar y continuar registra el progreso hasta completarlo', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    $this->putJson("/api/v1/app/{$e['slug']}/onboarding", ['paso' => 'marca', 'datos' => ['color' => 'rojo']], conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonPath('data.completo', false);

    // El progreso persiste (se puede continuar después).
    $this->getJson("/api/v1/app/{$e['slug']}/onboarding", conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonPath('data.completo', false)
        ->assertJsonFragment(['completados' => ['marca']]);

    // Completar todos los pasos → onboarding completo.
    foreach (['marca', 'sucursal', 'horarios', 'actividades', 'productos', 'politicas', 'pasarela', 'personal', 'publicacion'] as $paso) {
        $this->putJson("/api/v1/app/{$e['slug']}/onboarding", ['paso' => $paso], conBearer($e['bearer']))->assertOk();
    }

    $this->getJson("/api/v1/app/{$e['slug']}/onboarding", conBearer($e['bearer']))
        ->assertOk()
        ->assertJsonPath('data.completo', true);
});
