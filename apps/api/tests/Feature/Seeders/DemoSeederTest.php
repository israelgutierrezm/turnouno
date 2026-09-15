<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DemoSeeder;

it('no siembra cuentas demo en produccion (SEC-02)', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');

    (new DemoSeeder)->setContainer($this->app)->run();

    expect(User::query()->where('email', 'owner@turnouno.test')->exists())->toBeFalse();
});

it('siembra cuentas demo fuera de produccion', function (): void {
    $this->seed(DemoSeeder::class);

    expect(User::query()->where('email', 'owner@turnouno.test')->exists())->toBeTrue();
});
