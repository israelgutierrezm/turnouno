<?php

declare(strict_types=1);

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\Models\Estudio;
use App\Modules\Tenancy\Support\AlmacenamientoTenant;
use App\Modules\Tenancy\Support\CacheTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('tenants'));
});

afterEach(function (): void {
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));
});

/**
 * @return array{0: Estudio, 1: Estudio}
 */
function dosEstudios(): array
{
    estudioConSesion('estudio-a', 'a@correo.mx');
    estudioConSesion('estudio-b', 'b@correo.mx');

    return [
        Estudio::query()->where('slug', 'estudio-a')->firstOrFail(),
        Estudio::query()->where('slug', 'estudio-b')->firstOrFail(),
    ];
}

it('el cache esta aislado por estudio', function (): void {
    [$a, $b] = dosEstudios();
    $gestor = app(GestorDeConexionTenant::class);
    $cache = app(CacheTenant::class);

    $gestor->ejecutarEn($a, fn () => $cache->poner('saludo', 'hola-a', 60));
    $gestor->ejecutarEn($b, fn () => $cache->poner('saludo', 'hola-b', 60));

    // Misma clave logica, valores distintos por estudio.
    expect($gestor->ejecutarEn($a, fn () => $cache->obtener('saludo')))->toBe('hola-a');
    expect($gestor->ejecutarEn($b, fn () => $cache->obtener('saludo')))->toBe('hola-b');
    // La clave namespaced incluye el id del estudio.
    expect($gestor->ejecutarEn($a, fn () => $cache->clave('saludo')))->toBe("estudio:{$a->id}:saludo");
});

it('el almacenamiento esta aislado por estudio (rutas namespaced)', function (): void {
    [$a, $b] = dosEstudios();
    $gestor = app(GestorDeConexionTenant::class);
    $alm = app(AlmacenamientoTenant::class);

    $rutaA = $gestor->ejecutarEn($a, fn () => $alm->ruta('documentos/f.pdf'));
    $rutaB = $gestor->ejecutarEn($b, fn () => $alm->ruta('documentos/f.pdf'));

    expect($rutaA)->toBe("estudios/{$a->id}/documentos/f.pdf");
    expect($rutaB)->toBe("estudios/{$b->id}/documentos/f.pdf");
    expect($rutaA)->not->toBe($rutaB);
});

it('guarda un archivo en el espacio del estudio', function (): void {
    Storage::fake((string) config('filesystems.default'));
    [$a] = dosEstudios();
    $gestor = app(GestorDeConexionTenant::class);
    $alm = app(AlmacenamientoTenant::class);

    $ruta = $gestor->ejecutarEn($a, fn (): string => $alm->guardar(
        UploadedFile::fake()->create('comprobante.pdf', 5),
        'documentos',
        'comprobante.pdf',
    ));

    expect($ruta)->toContain("estudios/{$a->id}/documentos");
    Storage::disk((string) config('filesystems.default'))->assertExists($ruta);
});

it('los jobs capturan el estudio activo en su payload (aislamiento de colas)', function (): void {
    config()->set('queue.default', 'database');
    [$a] = dosEstudios();
    $gestor = app(GestorDeConexionTenant::class);

    // El alta encola correos de activacion (transaccionales); aqui solo interesa el
    // job que despachamos, asi que aislamos la tabla antes de encolarlo.
    DB::table('jobs')->delete();

    $gestor->ejecutarEn($a, function (): void {
        dispatch(function (): void {
            // noop: solo interesa el payload capturado al encolar.
        })->onConnection('database');
    });

    $fila = DB::table('jobs')->first();
    expect($fila)->not->toBeNull();
    /** @var array<string, mixed> $payload */
    $payload = json_decode((string) $fila->payload, true);
    expect($payload['estudio_id'] ?? null)->toBe($a->id);
});
