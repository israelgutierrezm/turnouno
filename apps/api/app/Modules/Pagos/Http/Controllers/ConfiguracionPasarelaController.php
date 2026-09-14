<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Http\Controllers;

use App\Modules\Pagos\Http\Requests\ConfigurarPasarelaRequest;
use App\Modules\Pagos\Models\ConfiguracionPasarela;
use App\Modules\Pagos\Pasarelas\RegistroDePasarelas;
use App\Modules\Pagos\ProveedorPasarela;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Administración de pasarelas por tenant: encender/apagar, modo (test/live) y
 * capturar llaves. Las credenciales NUNCA se devuelven (solo se listan los
 * nombres de llave configurados). Solo `pagos.configurar` (propietario).
 */
class ConfiguracionPasarelaController
{
    public function index(): JsonResponse
    {
        Gate::authorize('pagos.configurar');

        $configs = ConfiguracionPasarela::query()->get()->keyBy('proveedor');

        $data = array_map(function (string $proveedor) use ($configs): array {
            $config = $configs->get($proveedor);

            return [
                'proveedor' => $proveedor,
                'activa' => $config instanceof ConfiguracionPasarela ? $config->activa : false,
                'modo' => $config instanceof ConfiguracionPasarela ? $config->modo : 'test',
                'llaves_configuradas' => $config instanceof ConfiguracionPasarela
                    ? $this->llavesConfiguradas($config)
                    : [],
            ];
        }, $this->configurables());

        return response()->json(['data' => $data]);
    }

    /**
     * Pasarelas disponibles para cobrar en el tenant (para poblar el selector de
     * cobro). Requiere `pagos.crear`, no `pagos.configurar`.
     */
    public function activas(RegistroDePasarelas $registro): JsonResponse
    {
        Gate::authorize('pagos.crear');

        return response()->json(['data' => $registro->disponibles()]);
    }

    public function upsert(ConfigurarPasarelaRequest $request, string $proveedor): JsonResponse
    {
        Gate::authorize('pagos.configurar');
        abort_unless(in_array($proveedor, $this->configurables(), true), 404);

        $config = ConfiguracionPasarela::query()->firstOrNew(['proveedor' => $proveedor]);
        $config->activa = (bool) $request->validated('activa');
        $config->modo = (string) $request->validated('modo');

        $credenciales = $request->validated('credenciales');
        if (is_array($credenciales)) {
            // Merge: solo actualiza las llaves provistas; conserva las demás
            // (para no borrar secretos que no se reenvían en cada edición).
            $nuevas = [];
            foreach ($credenciales as $nombre => $valor) {
                if (is_string($valor) && $valor !== '') {
                    $nuevas[(string) $nombre] = $valor;
                }
            }
            $config->credenciales = array_merge($config->llaves(), $nuevas);
        }

        $config->save();

        return response()->json([
            'data' => [
                'proveedor' => $proveedor,
                'activa' => $config->activa,
                'modo' => $config->modo,
                'llaves_configuradas' => $this->llavesConfiguradas($config),
            ],
        ]);
    }

    /**
     * Proveedores configurables (los en línea + ventanilla). Manual y simulada son
     * integrados y no se configuran.
     *
     * @return list<string>
     */
    private function configurables(): array
    {
        return array_merge(ProveedorPasarela::enLinea(), [ProveedorPasarela::Ventanilla->value]);
    }

    /**
     * Nombres de las llaves configuradas (nunca sus valores).
     *
     * @return list<string>
     */
    private function llavesConfiguradas(ConfiguracionPasarela $config): array
    {
        return array_keys(array_filter(
            $config->llaves(),
            static fn (string $valor): bool => $valor !== '',
        ));
    }
}
