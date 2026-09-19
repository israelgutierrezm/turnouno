<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\DatosFiscalesTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Datos fiscales del emisor (el estudio) para CFDI, tenant-local. Cada tenant carga
 * los suyos; la llave de su organización FacturAPI nunca se devuelve (solo se informa
 * si ya está conectado). Opera sobre la BD del estudio resuelto.
 */
class DatosFiscalesTenantController
{
    public function show(): JsonResponse
    {
        $datos = DatosFiscalesTenant::query()->first();

        return response()->json(['data' => $datos instanceof DatosFiscalesTenant ? $this->presentar($datos) : null]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            // RFC persona moral (12) o física (13).
            'rfc' => ['required', 'string', 'regex:/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/i'],
            'regimen_fiscal' => ['required', 'string', 'regex:/^[0-9]{3,4}$/'],
            'codigo_postal' => ['required', 'string', 'regex:/^[0-9]{5}$/'],
        ]);

        $datos = DatosFiscalesTenant::query()->firstOrNew([]);
        $datos->fill([
            'razon_social' => $validado['razon_social'],
            'rfc' => mb_strtoupper((string) $validado['rfc']),
            'regimen_fiscal' => $validado['regimen_fiscal'],
            'codigo_postal' => $validado['codigo_postal'],
        ])->save();

        return response()->json(['data' => $this->presentar($datos)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(DatosFiscalesTenant $datos): array
    {
        return [
            'id' => $datos->ulid,
            'razon_social' => $datos->razon_social,
            'rfc' => $datos->rfc,
            'regimen_fiscal' => $datos->regimen_fiscal,
            'codigo_postal' => $datos->codigo_postal,
            'facturapi_conectado' => $datos->facturapiConectado(),
        ];
    }
}
