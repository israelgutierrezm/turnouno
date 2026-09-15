<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Tenancy\Application\CrearTenant;
use App\Modules\Tenancy\Application\VincularUsuarioATenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de demostración: tenant "Estudio Demo" + los verticales piloto. Crea
 * propietarios con contraseña conocida para explorar el producto, por lo que
 * NUNCA debe ejecutarse en producción (SEC-02). La contraseña puede fijarse con
 * `DEMO_PASSWORD` (por defecto `password`).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoSeeder omitido: no se siembran datos demo en producción.');

            return;
        }

        $password = (string) (env('DEMO_PASSWORD') ?? 'password');

        $tenant = app(CrearTenant::class)->ejecutar('Estudio Demo', 'estudio-demo');

        $propietario = User::firstOrCreate(
            ['email' => 'owner@turnouno.test'],
            ['name' => 'Dueño Demo', 'password' => Hash::make($password)],
        );

        app(VincularUsuarioATenant::class)->ejecutar($tenant, $propietario, ['propietario']);

        // Tenants demostrativos de los verticales piloto (pole, natación, gym).
        $this->call(PilotosSeeder::class);
    }
}
