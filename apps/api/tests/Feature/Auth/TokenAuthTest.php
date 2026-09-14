<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('emite un token de acceso personal con credenciales validas', function (): void {
    User::factory()->create([
        'email' => 'coach@turnouno.test',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/v1/auth/token', [
        'email' => 'coach@turnouno.test',
        'password' => 'secret123',
        'device_name' => 'iphone-15',
    ])
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
});

it('rechaza credenciales invalidas con el contrato de validacion estable', function (): void {
    User::factory()->create([
        'email' => 'coach@turnouno.test',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/v1/auth/token', [
        'email' => 'coach@turnouno.test',
        'password' => 'contrasena-incorrecta',
        'device_name' => 'iphone-15',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED');
});

it('exige autenticacion en /me', function (): void {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});
