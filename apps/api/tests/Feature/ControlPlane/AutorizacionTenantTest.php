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

it('el propietario invita personal con rol; el instructor no puede definir tipos de documento', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx'); // propietario

    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    // El instructor NO tiene documentos.gestionar → 403.
    $this->postJson("/api/v1/app/{$e['slug']}/tipos-documento", ['nombre' => 'INE'], conBearer($coach))
        ->assertStatus(403);

    // Pero sí puede responder formularios (formularios.responder) → 200.
    $this->getJson("/api/v1/app/{$e['slug']}/formularios", conBearer($coach))->assertOk();

    // El propietario sí puede definir tipos.
    $this->postJson("/api/v1/app/{$e['slug']}/tipos-documento", ['nombre' => 'INE'], conBearer($e['bearer']))
        ->assertCreated();
});

it('los roles son POR TENANT: el mismo correo es propietario en un estudio e instructor en otro', function (): void {
    $a = estudioConSesion('estudio-a', 'ana@correo.mx'); // ana = propietario en A
    $b = estudioConSesion('estudio-b', 'beto@correo.mx');

    // En B, ana@ es invitada como instructor (cuenta distinta).
    $anaEnB = personalConSesion($b['slug'], $b['bearer'], 'ana@correo.mx', 'instructor');

    // En A (propietario) ana@ define tipos → 201.
    $this->postJson("/api/v1/app/{$a['slug']}/tipos-documento", ['nombre' => 'INE'], conBearer($a['bearer']))
        ->assertCreated();

    // En B (instructor) ana@ NO puede → 403. Roles independientes por tenant.
    $this->postJson("/api/v1/app/{$b['slug']}/tipos-documento", ['nombre' => 'INE'], conBearer($anaEnB))
        ->assertStatus(403);
});
