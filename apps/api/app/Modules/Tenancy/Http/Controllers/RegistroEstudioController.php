<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Application\ActivacionPropietario;
use App\Modules\Tenancy\Application\AprovisionarEstudio;
use App\Modules\Tenancy\Application\RegistrarEstudio;
use App\Modules\Tenancy\Http\Requests\RegistrarEstudioRequest;
use App\Modules\Tenancy\Models\Estudio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Alta pública de un estudio (self-service). Crea el registro central, aprovisiona
 * su BD de tenant y su propietario tenant-local, y devuelve el enlace de acceso.
 * El token de activación se envía por correo en producción (aquí se retorna solo
 * fuera de producción para poder probar el flujo).
 */
class RegistroEstudioController
{
    public function __construct(
        private readonly RegistrarEstudio $registrar,
        private readonly AprovisionarEstudio $aprovisionar,
        private readonly ActivacionPropietario $activacion,
    ) {}

    public function disponibilidad(Request $request): JsonResponse
    {
        $slug = Str::slug((string) $request->query('slug', ''));
        $disponible = $slug !== '' && ! Estudio::query()->where('slug', $slug)->exists();

        return response()->json(['data' => ['slug' => $slug, 'disponible' => $disponible]]);
    }

    public function store(RegistrarEstudioRequest $request): JsonResponse
    {
        $estudio = $this->registrar->ejecutar([
            'nombre' => (string) $request->validated('nombre'),
            'slug' => (string) $request->validated('slug'),
            'contacto_nombre' => (string) $request->validated('contacto_nombre'),
            'contacto_email' => (string) $request->validated('contacto_email'),
            'contacto_telefono' => $request->validated('contacto_telefono'),
            'pais' => $request->validated('pais'),
            'ciudad' => $request->validated('ciudad'),
            'zona_horaria' => $request->validated('zona_horaria'),
        ]);

        // BD del tenant creada de forma síncrona (SQLite barato). En producción con
        // MySQL esto se despacharía a una cola; el estado permite reanudar.
        $this->aprovisionar->ejecutar($estudio);
        $token = $this->activacion->generar($estudio);

        return response()->json([
            'data' => [
                'estudio' => [
                    'slug' => $estudio->slug,
                    'nombre' => $estudio->nombre,
                    'estado' => $estudio->estado->value,
                    'url' => url('/app/'.$estudio->slug),
                ],
                'activacion' => app()->environment('production') ? null : [
                    'email' => $estudio->contacto_email,
                    'token' => $token,
                ],
            ],
        ], 201);
    }
}
