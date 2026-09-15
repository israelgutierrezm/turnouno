<?php

declare(strict_types=1);

it('limita los intentos de login por identidad+IP (SEC-03)', function (): void {
    $credenciales = ['email' => 'brute@x.mx', 'password' => 'incorrecta'];

    // Se permiten 5 intentos por minuto (identidad+IP); cada uno falla la validación.
    foreach (range(1, 5) as $ignorado) {
        $this->postJson('/api/v1/auth/login', $credenciales)->assertStatus(422);
    }

    // El 6.º intento queda bloqueado por el rate limiter.
    $this->postJson('/api/v1/auth/login', $credenciales)->assertStatus(429);
});
