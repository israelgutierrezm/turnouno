<?php

declare(strict_types=1);

it('devuelve el correlation id entrante cuando es seguro', function (): void {
    $this->getJson('/api/v1/health', ['X-Correlation-ID' => 'abc-123-DEF'])
        ->assertHeader('X-Correlation-ID', 'abc-123-DEF');
});

it('genera un correlation id cuando no se envia', function (): void {
    $respuesta = $this->getJson('/api/v1/health');

    expect($respuesta->headers->get('X-Correlation-ID'))->not->toBeEmpty();
});

it('rechaza un correlation id inseguro y genera uno nuevo', function (): void {
    $respuesta = $this->getJson('/api/v1/health', ['X-Correlation-ID' => "bad\r\nheader value"]);

    expect($respuesta->headers->get('X-Correlation-ID'))->not->toBe("bad\r\nheader value");
});
