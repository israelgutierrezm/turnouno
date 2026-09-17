<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Application\WaiversTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\WaiverTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Waivers / consentimientos del estudio (R27): publica versiones y consulta las
 * vigentes; tambien qué waivers le faltan por firmar a un miembro. La aceptacion la
 * hace la propia persona (autoservicio). Opera SIEMPRE sobre la BD del estudio resuelto.
 */
class WaiversTenantController
{
    public function __construct(private readonly WaiversTenant $waivers) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->waivers->vigentes()->map(fn (WaiverTenant $w): array => $this->presentar($w))->all(),
        ]);
    }

    public function publicar(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'clave' => ['required', 'string', 'max:100'],
            'titulo' => ['required', 'string', 'max:255'],
            'contenido' => ['required', 'string', 'max:20000'],
        ]);

        $waiver = $this->waivers->publicar($validado['clave'], $validado['titulo'], $validado['contenido']);

        return response()->json(['data' => $this->presentar($waiver)], 201);
    }

    public function pendientesDePersona(Request $request): JsonResponse
    {
        $persona = PersonaTenant::query()->where('ulid', (string) $request->route('persona'))->firstOrFail();

        return response()->json([
            'data' => $this->waivers->pendientesDe($persona)->map(fn (WaiverTenant $w): array => $this->presentar($w))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(WaiverTenant $waiver): array
    {
        return [
            'id' => $waiver->ulid,
            'clave' => $waiver->clave,
            'titulo' => $waiver->titulo,
            'version' => $waiver->version,
            'hash' => $waiver->hash,
        ];
    }
}
