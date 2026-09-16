<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\IntegracionTenant;
use App\Modules\Tenancy\ProveedorPartner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configuracion de integraciones de bienestar del estudio (Wellhub / TotalPass): el
 * propietario conecta sus credenciales. Se guardan cifradas y NUNCA se devuelven:
 * solo se informa que llaves estan configuradas. Solo propietario.
 */
class IntegracionesTenantController
{
    public function index(): JsonResponse
    {
        $configs = IntegracionTenant::query()->get()->keyBy('proveedor');

        $data = array_map(function (string $proveedor) use ($configs): array {
            $config = $configs->get($proveedor);

            return [
                'proveedor' => $proveedor,
                'activa' => $config instanceof IntegracionTenant ? $config->activa : false,
                'llaves_configuradas' => $config instanceof IntegracionTenant ? array_keys($config->llaves()) : [],
            ];
        }, ProveedorPartner::valores());

        return response()->json(['data' => $data]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $proveedor = (string) $request->route('proveedor');
        abort_unless(in_array($proveedor, ProveedorPartner::valores(), true), 404);

        $validado = $request->validate([
            'activa' => ['required', 'boolean'],
            'credenciales' => ['nullable', 'array'],
            'credenciales.*' => ['nullable', 'string'],
        ]);

        $config = IntegracionTenant::query()->firstOrNew(['proveedor' => $proveedor]);
        $config->activa = (bool) $validado['activa'];

        // Merge: solo actualiza las llaves provistas con valor; conserva las demas.
        $credenciales = $validado['credenciales'] ?? null;
        if (is_array($credenciales)) {
            $nuevas = [];
            foreach ($credenciales as $nombre => $valor) {
                if (is_string($valor) && $valor !== '') {
                    $nuevas[(string) $nombre] = $valor;
                }
            }
            $config->credenciales = array_merge($config->llaves(), $nuevas);
        }

        $config->save();

        return response()->json(['data' => [
            'proveedor' => $proveedor,
            'activa' => $config->activa,
            'llaves_configuradas' => array_keys($config->llaves()),
        ]]);
    }
}
