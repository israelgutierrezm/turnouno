<?php

declare(strict_types=1);

it('devuelve el contrato de error estable para rutas api desconocidas', function (): void {
    $this->getJson('/api/v1/no-existe')
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND')
        ->assertJsonStructure(['code', 'message']);
});

it('devuelve UNAUTHENTICATED en rutas protegidas sin sesion ni token', function (): void {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});
