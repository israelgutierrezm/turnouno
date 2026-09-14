<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('registra un dependiente bajo un tutor y lo muestra en la vista de hogar', function (): void {
    $tenant = crearTenant('AquaKids');
    $user = User::factory()->create();
    vincularUsuario($tenant, $user, ['propietario']);

    Sanctum::actingAs($user);

    $hogarUlid = $this->postJson('/api/v1/hogares', ['nombre' => 'Familia Rios'])
        ->assertCreated()
        ->json('data.id');

    $tutorUlid = $this->postJson('/api/v1/personas', [
        'nombre' => 'Maria',
        'apellidos' => 'Rios',
        'hogar_id' => $hogarUlid,
        'perfiles' => ['tutor'],
    ])->assertCreated()->json('data.id');

    // La madre (tutor, compradora) registra a su hija (dependiente, participante).
    $this->postJson("/api/v1/personas/{$tutorUlid}/dependientes", [
        'nombre' => 'Sofia',
        'apellidos' => 'Rios',
        'parentesco' => 'madre',
    ])
        ->assertCreated()
        ->assertJsonPath('data.nombre', 'Sofia');

    $respuesta = $this->getJson("/api/v1/hogares/{$hogarUlid}")->assertOk();

    expect($respuesta->json('data.personas'))->toHaveCount(2);

    $personas = collect($respuesta->json('data.personas'));
    $maria = $personas->firstWhere('nombre', 'Maria');
    $sofia = $personas->firstWhere('nombre', 'Sofia');

    expect($maria['perfiles'])->toContain('tutor');
    expect($maria['dependientes'])->toHaveCount(1);
    expect($sofia['perfiles'])->toContain('miembro');
});
