<?php

declare(strict_types=1);

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\Models\Estudio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

it('la marca del estudio es publica: nombre y logo sin autenticar', function (): void {
    $e = estudioConSesion('estudio-marca', 'a@correo.mx');

    $this->getJson("/api/v1/app/{$e['slug']}/marca")
        ->assertOk()
        ->assertJsonPath('data.slug', 'estudio-marca')
        ->assertJsonPath('data.nombre', 'Estudio estudio-marca')
        ->assertJsonPath('data.logo_url', null);
});

it('el administrador sube el logo del estudio', function (): void {
    Storage::fake('public');
    $e = estudioConSesion('estudio-logo', 'a@correo.mx');

    $this->postJson("/api/v1/app/{$e['slug']}/marca/logo", [
        'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
    ], conBearer($e['bearer']))->assertOk();

    $estudio = Estudio::query()->where('slug', 'estudio-logo')->firstOrFail();
    expect($estudio->logo_url)->not->toBeNull();

    // La marca publica ya expone el logo.
    $this->getJson("/api/v1/app/{$e['slug']}/marca")
        ->assertOk()
        ->assertJsonPath('data.logo_url', $estudio->logo_url);
});

it('un rol sin estudio.gestionar no puede subir el logo', function (): void {
    Storage::fake('public');
    $e = estudioConSesion('estudio-rbac', 'a@correo.mx');
    $recepcion = personalConSesion($e['slug'], $e['bearer'], 'recep@correo.mx', 'recepcionista');

    $this->postJson("/api/v1/app/{$e['slug']}/marca/logo", [
        'logo' => UploadedFile::fake()->image('logo.png'),
    ], conBearer($recepcion))->assertForbidden();
});

it('rechaza archivos que no son imagen', function (): void {
    Storage::fake('public');
    $e = estudioConSesion('estudio-mime', 'a@correo.mx');

    $this->postJson("/api/v1/app/{$e['slug']}/marca/logo", [
        'logo' => UploadedFile::fake()->create('malicioso.pdf', 100, 'application/pdf'),
    ], conBearer($e['bearer']))->assertStatus(422);
});
