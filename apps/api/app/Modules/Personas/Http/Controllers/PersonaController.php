<?php

declare(strict_types=1);

namespace App\Modules\Personas\Http\Controllers;

use App\Modules\Personas\Application\CrearPersona;
use App\Modules\Personas\Http\Requests\CrearPersonaRequest;
use App\Modules\Personas\Models\Perfil;
use App\Modules\Personas\Models\Persona;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Personas del tenant activo y sus perfiles. El global scope de BelongsToTenant
 * mantiene el aislamiento (ADR-0007).
 */
class PersonaController
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('miembros.ver');

        $query = Persona::query()->with('perfiles')->orderBy('id');

        $perfil = $request->query('perfil');
        if (is_string($perfil) && $perfil !== '') {
            $query->whereHas('perfiles', function (Builder $consulta) use ($perfil): void {
                $consulta->where('tipo', $perfil);
            });
        }

        return response()->json([
            'data' => $query->get()->map(fn (Persona $persona): array => $this->presentar($persona))->all(),
        ]);
    }

    public function show(Persona $persona): JsonResponse
    {
        Gate::authorize('miembros.ver');

        $persona->load('perfiles');

        return response()->json(['data' => $this->presentar($persona)]);
    }

    public function store(CrearPersonaRequest $request, CrearPersona $crearPersona): JsonResponse
    {
        Gate::authorize('miembros.crear');

        $persona = $crearPersona->ejecutar(
            (string) $request->validated('nombre'),
            $this->comoTexto($request->validated('apellidos')),
            $this->comoTexto($request->validated('email')),
            $this->comoTexto($request->validated('fecha_nacimiento')),
            $this->comoListaDeTextos($request->validated('perfiles')),
        );

        return response()->json(['data' => $this->presentar($persona->load('perfiles'))], 201);
    }

    private function comoTexto(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    /**
     * @return list<string>
     */
    private function comoListaDeTextos(mixed $valor): array
    {
        return is_array($valor) ? array_values(array_filter($valor, 'is_string')) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Persona $persona): array
    {
        return [
            'id' => $persona->ulid,
            'nombre' => $persona->nombre,
            'apellidos' => $persona->apellidos,
            'email' => $persona->email,
            'fecha_nacimiento' => $persona->fecha_nacimiento?->toDateString(),
            'perfiles' => $persona->perfiles
                ->map(static fn (Perfil $perfil): string => $perfil->tipo->value)
                ->all(),
        ];
    }
}
