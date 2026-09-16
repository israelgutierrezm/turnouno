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

it('un estudio cuya BD no existe responde 404 limpio, no 500', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');

    // Simula la BD del tenant borrada (p. ej. limpieza de storage/tenants en dev).
    app(GestorDeConexionTenant::class)->desconectar();
    File::deleteDirectory(storage_path('tenants'));

    $this->postJson("/api/v1/app/{$e['slug']}/login", [
        'email' => 'a@correo.mx', 'password' => 'secreto123',
    ])->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

    // Tampoco filtra un 500 en rutas autenticadas.
    $this->getJson("/api/v1/app/{$e['slug']}/yo", conBearer($e['bearer']))
        ->assertStatus(404);
});

it('un slug inexistente sigue respondiendo 404', function (): void {
    $this->postJson('/api/v1/app/no-existe/login', [
        'email' => 'x@correo.mx', 'password' => 'secreto123',
    ])->assertStatus(404);
});
