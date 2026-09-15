<?php

declare(strict_types=1);

use App\Modules\Tenancy\Application\ActivacionPropietario;
use App\Modules\Tenancy\Application\AprovisionarEstudio;
use App\Modules\Tenancy\Application\RegistrarEstudio;
use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\Exceptions\SlugNoDisponible;
use App\Modules\Tenancy\Models\Estudio;
use App\Modules\Tenancy\Models\Usuario;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

function estudioAprovisionado(string $nombre, string $slug, string $email): Estudio
{
    $estudio = app(RegistrarEstudio::class)->ejecutar([
        'nombre' => $nombre,
        'slug' => $slug,
        'contacto_nombre' => 'Dueño '.$nombre,
        'contacto_email' => $email,
    ]);

    return app(AprovisionarEstudio::class)->ejecutar($estudio);
}

it('provisiona dos estudios en bases separadas; el mismo email es una cuenta distinta por tenant', function (): void {
    $a = estudioAprovisionado('Estudio A', 'estudio-a', 'ana@correo.mx');
    $b = estudioAprovisionado('Estudio B', 'estudio-b', 'ana@correo.mx'); // MISMO correo

    expect($a->db_database)->not->toBe($b->db_database);
    expect($a->estado->value)->toBe('trialing');
    expect($a->trial_termina_en)->not->toBeNull();

    $gestor = app(GestorDeConexionTenant::class);
    $usuarioA = $gestor->ejecutarEn($a, fn () => Usuario::query()->where('email', 'ana@correo.mx')->firstOrFail());
    $usuarioB = $gestor->ejecutarEn($b, fn () => Usuario::query()->where('email', 'ana@correo.mx')->firstOrFail());

    // Cuentas independientes: mismo email, ulids distintos.
    expect($usuarioA->email)->toBe($usuarioB->email);
    expect($usuarioA->ulid)->not->toBe($usuarioB->ulid);

    // Aislamiento de datos: cada base ve solo a su propio usuario.
    expect($gestor->ejecutarEn($a, fn () => Usuario::query()->count()))->toBe(1);
    expect($gestor->ejecutarEn($b, fn () => Usuario::query()->count()))->toBe(1);
});

it('cambiar la contraseña en un tenant no afecta al otro', function (): void {
    $a = estudioAprovisionado('Estudio A', 'estudio-a', 'ana@correo.mx');
    $b = estudioAprovisionado('Estudio B', 'estudio-b', 'ana@correo.mx');

    $activacion = app(ActivacionPropietario::class);
    $activacion->activar($a, 'ana@correo.mx', $activacion->generar($a), 'passwordA');
    $activacion->activar($b, 'ana@correo.mx', $activacion->generar($b), 'passwordB');

    $gestor = app(GestorDeConexionTenant::class);
    $hashA = (string) $gestor->ejecutarEn($a, fn () => Usuario::query()->where('email', 'ana@correo.mx')->value('password'));
    $hashB = (string) $gestor->ejecutarEn($b, fn () => Usuario::query()->where('email', 'ana@correo.mx')->value('password'));

    expect(Hash::check('passwordA', $hashA))->toBeTrue();
    expect(Hash::check('passwordB', $hashB))->toBeTrue();
    expect(Hash::check('passwordA', $hashB))->toBeFalse();
});

it('el provisioning es idempotente (no duplica propietario)', function (): void {
    $estudio = app(RegistrarEstudio::class)->ejecutar([
        'nombre' => 'Estudio A', 'slug' => 'estudio-a',
        'contacto_nombre' => 'Dueño', 'contacto_email' => 'ana@correo.mx',
    ]);

    app(AprovisionarEstudio::class)->ejecutar($estudio);
    app(AprovisionarEstudio::class)->ejecutar($estudio->refresh()); // re-ejecutar

    expect(app(GestorDeConexionTenant::class)->ejecutarEn($estudio, fn () => Usuario::query()->count()))->toBe(1);
});

it('rechaza un slug ya tomado (SLUG_TAKEN)', function (): void {
    app(RegistrarEstudio::class)->ejecutar([
        'nombre' => 'A', 'slug' => 'pole', 'contacto_nombre' => 'X', 'contacto_email' => 'a@b.mx',
    ]);

    app(RegistrarEstudio::class)->ejecutar([
        'nombre' => 'B', 'slug' => 'pole', 'contacto_nombre' => 'Y', 'contacto_email' => 'c@d.mx',
    ]);
})->throws(SlugNoDisponible::class);

it('ejecutarEn restaura el estado activo en finally (no filtra conexión entre tenants)', function (): void {
    $a = estudioAprovisionado('Estudio A', 'estudio-a', 'ana@correo.mx');
    $gestor = app(GestorDeConexionTenant::class);

    expect($gestor->actual())->toBeNull();
    $gestor->ejecutarEn($a, fn () => Usuario::query()->count());
    expect($gestor->actual())->toBeNull();
});
