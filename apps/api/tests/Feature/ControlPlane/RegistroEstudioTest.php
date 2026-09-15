<?php

declare(strict_types=1);

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
 * @return array{slug: string, email: string, token: string}
 */
function registrarEstudioApi(string $nombre, string $slug, string $email): array
{
    $resp = test()->postJson('/api/v1/registro', [
        'nombre' => $nombre,
        'slug' => $slug,
        'contacto_nombre' => 'Dueño',
        'contacto_email' => $email,
        'acepta_terminos' => true,
    ])->assertCreated();

    return [
        'slug' => (string) $resp->json('data.estudio.slug'),
        'email' => (string) $resp->json('data.activacion.email'),
        'token' => (string) $resp->json('data.activacion.token'),
    ];
}

it('recorrido: registro público → provisioning → activación → login tenant-local → yo', function (): void {
    $r = registrarEstudioApi('Pole House', 'pole-house', 'ana@correo.mx');

    // Estado trialing tras aprovisionar.
    expect($r['slug'])->toBe('pole-house');

    // Activación (fija la contraseña; sin contraseñas por defecto).
    $this->postJson("/api/v1/app/{$r['slug']}/activar", [
        'email' => $r['email'],
        'token' => $r['token'],
        'password' => 'secreto123',
        'password_confirmation' => 'secreto123',
    ])->assertCreated()->assertJsonPath('data.usuario.email', 'ana@correo.mx');

    // Login tenant-local.
    $bearer = (string) $this->postJson("/api/v1/app/{$r['slug']}/login", [
        'email' => $r['email'],
        'password' => 'secreto123',
    ])->assertOk()->json('data.token');

    // Perfil autenticado (identidad tenant-local).
    $this->getJson("/api/v1/app/{$r['slug']}/yo", ['Authorization' => "Bearer {$bearer}"])
        ->assertOk()
        ->assertJsonPath('data.usuario.email', 'ana@correo.mx')
        ->assertJsonPath('data.estudio.slug', 'pole-house');
});

it('el mismo correo puede registrarse en dos estudios distintos (cuentas independientes)', function (): void {
    registrarEstudioApi('Estudio A', 'estudio-a', 'ana@correo.mx');
    registrarEstudioApi('Estudio B', 'estudio-b', 'ana@correo.mx');

    // El correo no es global: dos estudios distintos con el mismo contacto.
    expect(Estudio::query()->where('contacto_email', 'ana@correo.mx')->count())->toBe(2);
});

it('un token de un estudio NO autentica en otro (aislamiento de sesión)', function (): void {
    $a = registrarEstudioApi('Estudio A', 'estudio-a', 'ana@correo.mx');
    $this->postJson("/api/v1/app/{$a['slug']}/activar", [
        'email' => $a['email'], 'token' => $a['token'],
        'password' => 'secreto123', 'password_confirmation' => 'secreto123',
    ])->assertCreated();
    $bearerA = (string) $this->postJson("/api/v1/app/{$a['slug']}/login", [
        'email' => $a['email'], 'password' => 'secreto123',
    ])->assertOk()->json('data.token');

    // Segundo estudio.
    $b = registrarEstudioApi('Estudio B', 'estudio-b', 'ana@correo.mx');

    // El token del estudio A no vale en el estudio B.
    $this->getJson("/api/v1/app/{$b['slug']}/yo", ['Authorization' => "Bearer {$bearerA}"])
        ->assertStatus(401);

    // Y sí vale en el suyo.
    $this->getJson("/api/v1/app/{$a['slug']}/yo", ['Authorization' => "Bearer {$bearerA}"])
        ->assertOk();
});

it('el slug se verifica por disponibilidad y no se repite', function (): void {
    registrarEstudioApi('Pole House', 'pole-house', 'ana@correo.mx');

    $this->getJson('/api/v1/registro/slug?slug=pole-house')
        ->assertOk()->assertJsonPath('data.disponible', false);

    $this->getJson('/api/v1/registro/slug?slug=otro-estudio')
        ->assertOk()->assertJsonPath('data.disponible', true);

    // Un segundo registro con el mismo slug es rechazado por validación.
    $this->postJson('/api/v1/registro', [
        'nombre' => 'Otro', 'slug' => 'pole-house',
        'contacto_nombre' => 'X', 'contacto_email' => 'x@y.mx', 'acepta_terminos' => true,
    ])->assertStatus(422);
});
