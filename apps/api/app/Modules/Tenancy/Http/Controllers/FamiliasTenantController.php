<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\HogarTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Familias del estudio (R26): hogares (agrupan a la familia) y tutelas (tutor →
 * dependiente). Modela comprador != participante y habilita que un tutor gestione a
 * sus dependientes. Opera SIEMPRE sobre la BD del estudio resuelto.
 */
class FamiliasTenantController
{
    public function crearHogar(Request $request): JsonResponse
    {
        $validado = $request->validate(['nombre' => ['required', 'string', 'max:255']]);

        $hogar = HogarTenant::query()->create(['nombre' => $validado['nombre']]);

        return response()->json(['data' => ['id' => $hogar->ulid, 'nombre' => $hogar->nombre]], 201);
    }

    public function asignarPersona(Request $request): JsonResponse
    {
        $hogar = HogarTenant::query()->where('ulid', (string) $request->route('hogar'))->firstOrFail();
        $validado = $request->validate(['persona_id' => ['required', 'string']]);

        $persona = PersonaTenant::query()->where('ulid', $validado['persona_id'])->firstOrFail();
        $persona->update(['hogar_id' => $hogar->getKey()]);

        return response()->json(['data' => [
            'hogar' => $hogar->ulid,
            'personas' => $hogar->personas()->get()->map(fn (PersonaTenant $p): array => [
                'id' => $p->ulid, 'nombre' => trim($p->nombre.' '.($p->apellidos ?? '')),
            ])->all(),
        ]]);
    }

    public function crearTutela(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'tutor_id' => ['required', 'string'],
            'dependiente_id' => ['required', 'string', 'different:tutor_id'],
            'parentesco' => ['nullable', 'string', 'max:100'],
        ]);

        $tutor = PersonaTenant::query()->where('ulid', $validado['tutor_id'])->firstOrFail();
        $dependiente = PersonaTenant::query()->where('ulid', $validado['dependiente_id'])->firstOrFail();

        if ($tutor->getKey() === $dependiente->getKey()) {
            throw ValidationException::withMessages(['dependiente_id' => 'El tutor y el dependiente no pueden ser la misma persona.']);
        }

        $tutor->dependientes()->syncWithoutDetaching([
            $dependiente->getKey() => ['parentesco' => $validado['parentesco'] ?? null],
        ]);

        return response()->json(['data' => [
            'tutor' => $tutor->ulid,
            'dependiente' => $dependiente->ulid,
            'parentesco' => $validado['parentesco'] ?? null,
        ]], 201);
    }

    public function dependientes(Request $request): JsonResponse
    {
        $tutor = PersonaTenant::query()->where('ulid', (string) $request->route('persona'))->firstOrFail();

        return response()->json([
            'data' => $tutor->dependientes()->get()->map(fn (PersonaTenant $p): array => [
                'id' => $p->ulid,
                'nombre' => trim($p->nombre.' '.($p->apellidos ?? '')),
                'parentesco' => data_get($p, 'pivot.parentesco'),
            ])->all(),
        ]);
    }
}
