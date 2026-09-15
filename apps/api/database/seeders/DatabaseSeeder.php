<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Datos canónicos (permisos), seguros para cualquier entorno.
        $this->call(PermisosSeeder::class);

        // Los datos demo (propietarios con contraseña conocida) NUNCA en producción (SEC-02).
        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
