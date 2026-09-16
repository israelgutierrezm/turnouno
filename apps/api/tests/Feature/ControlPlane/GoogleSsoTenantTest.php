<?php

declare(strict_types=1);

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
    config()->set('services.google.client_id', 'client-123.apps.googleusercontent.com');
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

/**
 * Simula la respuesta del endpoint `tokeninfo` de Google para un correo dado.
 */
function googleTokeninfo(
    string $email,
    string $aud = 'client-123.apps.googleusercontent.com',
    bool $verificado = true,
    int $estado = 200,
): void {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response([
            'iss' => 'https://accounts.google.com',
            'aud' => $aud,
            'sub' => 'google-sub-'.md5($email),
            'email' => $email,
            'email_verified' => $verificado ? 'true' : 'false',
            'name' => 'Usuario Google',
        ], $estado),
    ]);
}

it('inicia sesion con Google en una cuenta existente del estudio y emite bearer', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    googleTokeninfo('a@correo.mx');

    $r = $this->postJson("/api/v1/app/{$e['slug']}/auth/google", ['credential' => 'id-token-falso'])
        ->assertOk()
        ->assertJsonPath('data.usuario.email', 'a@correo.mx')
        ->assertJsonPath('data.usuario.rol', 'propietario');

    // El bearer emitido autentica en el estudio.
    $this->getJson("/api/v1/app/{$e['slug']}/yo", conBearer((string) $r->json('data.token')))
        ->assertOk()->assertJsonPath('data.usuario.email', 'a@correo.mx');
});

it('activa e inicia sesion a un usuario invitado no activado via Google', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    // Invita a un instructor (queda inactivo hasta activar).
    $this->postJson("/api/v1/app/{$e['slug']}/usuarios/invitar", [
        'nombre' => 'Coach', 'email' => 'coach@correo.mx', 'rol' => 'instructor',
    ], conBearer($e['bearer']))->assertCreated();

    googleTokeninfo('coach@correo.mx');

    // Sin activar por token: Google lo autentica (correo verificado) y activa.
    $this->postJson("/api/v1/app/{$e['slug']}/auth/google", ['credential' => 't'])
        ->assertOk()->assertJsonPath('data.usuario.rol', 'instructor');
});

it('rechaza Google si no hay cuenta con ese correo en el estudio (Google no crea cuentas)', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    googleTokeninfo('desconocido@correo.mx');

    $this->postJson("/api/v1/app/{$e['slug']}/auth/google", ['credential' => 't'])
        ->assertStatus(422)->assertJsonPath('code', 'GOOGLE_AUTH_FAILED');
});

it('rechaza un ID token cuyo aud no es esta app', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    googleTokeninfo('a@correo.mx', aud: 'otra-app.apps.googleusercontent.com');

    $this->postJson("/api/v1/app/{$e['slug']}/auth/google", ['credential' => 't'])
        ->assertStatus(422)->assertJsonPath('code', 'GOOGLE_AUTH_FAILED');
});

it('rechaza Google si el correo no esta verificado', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    googleTokeninfo('a@correo.mx', verificado: false);

    $this->postJson("/api/v1/app/{$e['slug']}/auth/google", ['credential' => 't'])
        ->assertStatus(422)->assertJsonPath('code', 'GOOGLE_AUTH_FAILED');
});

it('las cuentas de Google son por estudio: el mismo correo entra en uno y no en otro', function (): void {
    $a = estudioConSesion('estudio-a', 'comun@correo.mx');
    $b = estudioConSesion('estudio-b', 'otro@correo.mx');
    googleTokeninfo('comun@correo.mx');

    // En A el correo es el dueno: entra.
    $this->postJson("/api/v1/app/{$a['slug']}/auth/google", ['credential' => 't'])->assertOk();
    // En B ese correo no existe: rechazado.
    $this->postJson("/api/v1/app/{$b['slug']}/auth/google", ['credential' => 't'])->assertStatus(422);
});
