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

it('el listado de miembros pagina con meta', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    crearMiembroTenant($e, 'Uno');
    crearMiembroTenant($e, 'Dos');
    crearMiembroTenant($e, 'Tres');

    $r = $this->getJson("/api/v1/app/{$e['slug']}/miembros?page=1&per_page=2", conBearer($e['bearer']))->assertOk();

    $r->assertJsonCount(2, 'data');
    expect($r->json('meta.total'))->toBe(3);
    expect($r->json('meta.page'))->toBe(1);
    expect($r->json('meta.ultima_pagina'))->toBe(2);
});

it('editar un miembro lo suspende, archiva y marca no facturable', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $p = crearMiembroTenant($e, 'Ana');

    $r = $this->putJson("/api/v1/app/{$e['slug']}/miembros/{$p}", [
        'activo' => false, 'es_facturable' => false, 'archivado' => true,
    ], conBearer($e['bearer']))->assertOk();

    expect($r->json('data.activo'))->toBeFalse();
    expect($r->json('data.es_facturable'))->toBeFalse();
    expect($r->json('data.archivado'))->toBeTrue();
});

it('el padron facturable lista solo activos facturables no archivados', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    crearMiembroTenant($e, 'Facturable Uno');
    crearMiembroTenant($e, 'Facturable Dos');
    $noFact = crearMiembroTenant($e, 'No Factura');
    $this->putJson("/api/v1/app/{$e['slug']}/miembros/{$noFact}", ['es_facturable' => false], conBearer($e['bearer']))->assertOk();

    $r = $this->getJson("/api/v1/app/{$e['slug']}/miembros/padron", conBearer($e['bearer']))->assertOk();

    expect($r->json('meta.total'))->toBe(2);
    expect(collect($r->json('data'))->pluck('nombre_completo'))->not->toContain('No Factura');
});

it('el padron facturable exporta CSV', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    crearMiembroTenant($e, 'Ana Facturable');

    $r = $this->get("/api/v1/app/{$e['slug']}/miembros/padron?formato=csv", conBearer($e['bearer']))->assertOk();

    expect($r->headers->get('content-type'))->toContain('text/csv');
    expect($r->getContent())->toContain('Ana Facturable');
});

it('editar un miembro exige permiso de gestion', function (): void {
    $e = estudioConSesion('estudio-a', 'a@correo.mx');
    $p = crearMiembroTenant($e, 'Ana');
    $coach = personalConSesion($e['slug'], $e['bearer'], 'coach@correo.mx', 'instructor');

    $this->putJson("/api/v1/app/{$e['slug']}/miembros/{$p}", ['activo' => false], conBearer($coach))->assertForbidden();
});
