<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Tenancy\Application\CrearTenant;
use App\Modules\Tenancy\Application\VincularUsuarioATenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermisosSeeder::class);

        $tenant = app(CrearTenant::class)->ejecutar('Estudio Demo', 'estudio-demo');

        $propietario = User::firstOrCreate(
            ['email' => 'owner@turnouno.test'],
            ['name' => 'Dueño Demo', 'password' => Hash::make('password')],
        );

        app(VincularUsuarioATenant::class)->ejecutar($tenant, $propietario, ['propietario']);
    }
}
