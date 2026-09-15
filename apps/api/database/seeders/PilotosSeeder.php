<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Agenda\Application\CrearSesionUnica;
use App\Modules\Catalogo\Models\Actividad;
use App\Modules\Catalogo\Models\Oferta;
use App\Modules\Catalogo\Models\Programa;
use App\Modules\Membresias\Application\CrearAcuerdo;
use App\Modules\Membresias\Application\CrearProducto;
use App\Modules\Membresias\TipoProducto;
use App\Modules\Organizaciones\Application\CrearOrganizacion;
use App\Modules\Organizaciones\Application\CrearSucursal;
use App\Modules\Personas\Models\Persona;
use App\Modules\Tenancy\Application\CrearTenant;
use App\Modules\Tenancy\Application\VincularUsuarioATenant;
use App\Modules\Tenancy\Context\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Siembra los tres tenants demostrativos de los verticales piloto (pole,
 * natación, gym) sobre el MISMO core configurable (DEVELOPMENT_PLAN Slice 8).
 * Cada uno queda listo para explorar: catálogo, producto, un miembro con su
 * derecho y sesiones próximas para reservar. Contraseña de todos: `password`.
 */
class PilotosSeeder extends Seeder
{
    public function run(): void
    {
        // Defensa en profundidad: datos demo con contraseña conocida, nunca en prod (SEC-02).
        if (app()->environment('production')) {
            $this->command?->warn('PilotosSeeder omitido en producción.');

            return;
        }

        foreach ($this->verticales() as $config) {
            $this->sembrar($config);
        }
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function sembrar(array $c): void
    {
        // Idempotente: si el owner ya existe, este vertical ya fue sembrado.
        if (User::query()->where('email', $c['email'])->exists()) {
            return;
        }

        $tenant = app(CrearTenant::class)->ejecutar((string) $c['tenant'], (string) $c['slug']);

        $owner = User::create([
            'name' => (string) $c['owner'],
            'email' => (string) $c['email'],
            'password' => Hash::make((string) (env('DEMO_PASSWORD') ?? 'password')),
        ]);
        app(VincularUsuarioATenant::class)->ejecutar($tenant, $owner, ['propietario']);

        $contexto = app(TenantContext::class);
        $contexto->set($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $organizacion = app(CrearOrganizacion::class)->ejecutar((string) $c['org']);
        $sucursal = app(CrearSucursal::class)->ejecutar($organizacion, (string) $c['sucursal'], (string) $c['zona']);

        $programa = Programa::create(['nombre' => $c['programa'], 'slug' => $this->slug((string) $c['programa'])]);
        $actividad = Actividad::create([
            'programa_id' => $programa->id,
            'nombre' => $c['actividad'],
            'slug' => $this->slug((string) $c['actividad']),
        ]);
        $oferta = Oferta::create([
            'actividad_id' => $actividad->id,
            'nombre' => $c['oferta'],
            'modalidad' => $c['modalidad'],
            'capacidad' => $c['capacidad'],
        ]);

        $producto = app(CrearProducto::class)->ejecutar(
            (string) $c['producto'],
            $c['tipoProducto'],
            (int) $c['precio'],
            'MXN',
            (bool) $c['ilimitado'],
            $c['creditos'],
        );

        $miembro = Persona::create(['nombre' => $c['miembroNombre'], 'apellidos' => $c['miembroApellidos']]);
        app(CrearAcuerdo::class)->ejecutar($miembro, $producto);

        // Dos sesiones próximas para poder reservar en el demo.
        foreach ([7, 9] as $dias) {
            $cuando = CarbonImmutable::now((string) $c['zona'])->addDays($dias)->setTime(19, 0)->format('Y-m-d H:i');
            app(CrearSesionUnica::class)->ejecutar($oferta, $sucursal, [
                'inicia_en_local' => $cuando,
                'duracion_minutos' => 60,
                'capacidad' => (int) $c['capacidad'],
            ]);
        }

        $contexto->clear();
    }

    private function slug(string $nombre): string
    {
        return Str::slug($nombre).'-'.Str::lower(Str::random(5));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function verticales(): array
    {
        return [
            [
                'tenant' => 'Pole House', 'slug' => 'pole-house',
                'owner' => 'Dueña Pole', 'email' => 'pole@turnouno.test',
                'org' => 'Pole House Studios', 'sucursal' => 'Roma Norte', 'zona' => 'America/Mexico_City',
                'programa' => 'Pole', 'actividad' => 'Pole Fitness', 'oferta' => 'Clase grupal',
                'modalidad' => 'grupal', 'capacidad' => 8,
                'producto' => 'Pack 8 clases', 'tipoProducto' => TipoProducto::Paquete,
                'precio' => 89900, 'ilimitado' => false, 'creditos' => 8000,
                'miembroNombre' => 'Ana', 'miembroApellidos' => 'Ríos',
            ],
            [
                'tenant' => 'AquaKids', 'slug' => 'aquakids',
                'owner' => 'Dueño Aqua', 'email' => 'natacion@turnouno.test',
                'org' => 'AquaKids Escuela', 'sucursal' => 'Del Valle', 'zona' => 'America/Mexico_City',
                'programa' => 'Natación', 'actividad' => 'Natación infantil', 'oferta' => 'Clase privada',
                'modalidad' => 'privada', 'capacidad' => 3,
                'producto' => 'Pack 4 clases', 'tipoProducto' => TipoProducto::Paquete,
                'precio' => 60000, 'ilimitado' => false, 'creditos' => 4000,
                'miembroNombre' => 'Sofía', 'miembroApellidos' => 'López',
            ],
            [
                'tenant' => 'Iron Gym', 'slug' => 'iron-gym',
                'owner' => 'Dueño Iron', 'email' => 'gym@turnouno.test',
                'org' => 'Iron Gym', 'sucursal' => 'Condesa', 'zona' => 'America/Mexico_City',
                'programa' => 'Funcional', 'actividad' => 'Cross Training', 'oferta' => 'Sesión grupal',
                'modalidad' => 'grupal', 'capacidad' => 20,
                'producto' => 'Membresía mensual', 'tipoProducto' => TipoProducto::Membresia,
                'precio' => 79900, 'ilimitado' => true, 'creditos' => null,
                'miembroNombre' => 'Luis', 'miembroApellidos' => 'Marín',
            ],
        ];
    }
}
