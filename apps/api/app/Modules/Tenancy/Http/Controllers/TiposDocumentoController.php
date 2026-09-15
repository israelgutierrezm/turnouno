<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\TipoDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de documentos que el estudio requiere (definido por el administrador),
 * tenant-local. El admin controla qué documentos pedir a miembros/instructores.
 */
class TiposDocumentoController
{
    public function index(): JsonResponse
    {
        $tipos = TipoDocumento::query()->orderBy('id')->get();

        return response()->json([
            'data' => $tipos->map(fn (TipoDocumento $tipo): array => $this->presentar($tipo))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'obligatorio' => ['nullable', 'boolean'],
            'aplica_a' => ['nullable', 'in:miembro,instructor,todos'],
        ]);

        $tipo = TipoDocumento::query()->create([
            'nombre' => $validado['nombre'],
            'descripcion' => $validado['descripcion'] ?? null,
            'obligatorio' => (bool) ($validado['obligatorio'] ?? false),
            'aplica_a' => $validado['aplica_a'] ?? 'miembro',
            'activo' => true,
        ]);

        return response()->json(['data' => $this->presentar($tipo)], 201);
    }

    public function update(Request $request): JsonResponse
    {
        $modelo = TipoDocumento::query()->where('ulid', (string) $request->route('tipo'))->firstOrFail();

        $validado = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'obligatorio' => ['sometimes', 'boolean'],
            'aplica_a' => ['sometimes', 'in:miembro,instructor,todos'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $modelo->update($validado);

        return response()->json(['data' => $this->presentar($modelo->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(TipoDocumento $tipo): array
    {
        return [
            'id' => $tipo->ulid,
            'nombre' => $tipo->nombre,
            'descripcion' => $tipo->descripcion,
            'obligatorio' => $tipo->obligatorio,
            'aplica_a' => $tipo->aplica_a,
            'activo' => $tipo->activo,
        ];
    }
}
