<?php

declare(strict_types=1);

namespace App\Modules\Organizaciones\Http\Controllers;

use App\Modules\Organizaciones\Application\CrearSucursal;
use App\Modules\Organizaciones\Http\Requests\CrearSucursalRequest;
use App\Modules\Organizaciones\Models\Organizacion;
use App\Modules\Organizaciones\Models\Sucursal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SucursalController
{
    public function index(): JsonResponse
    {
        Gate::authorize('sucursales.ver');

        $sucursales = Sucursal::query()->orderBy('id')->get();

        return response()->json([
            'data' => $sucursales->map(fn (Sucursal $sucursal): array => $this->presentar($sucursal))->all(),
        ]);
    }

    public function show(Sucursal $sucursal): JsonResponse
    {
        Gate::authorize('sucursales.ver');

        return response()->json(['data' => $this->presentar($sucursal)]);
    }

    public function store(CrearSucursalRequest $request, CrearSucursal $crearSucursal): JsonResponse
    {
        Gate::authorize('sucursales.gestionar');

        // Resolvemos la organización por su ULID; el global scope evita fugas entre tenants.
        $organizacion = Organizacion::query()
            ->where('ulid', (string) $request->validated('organizacion_id'))
            ->firstOrFail();

        $zonaHoraria = $request->validated('zona_horaria');

        $sucursal = $crearSucursal->ejecutar(
            $organizacion,
            (string) $request->validated('nombre'),
            is_string($zonaHoraria) ? $zonaHoraria : null,
        );

        return response()->json(['data' => $this->presentar($sucursal)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Sucursal $sucursal): array
    {
        return [
            'id' => $sucursal->ulid,
            'nombre' => $sucursal->nombre,
            'slug' => $sucursal->slug,
            'zona_horaria' => $sucursal->zona_horaria,
            'estado' => $sucursal->estado,
        ];
    }
}
