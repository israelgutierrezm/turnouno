<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\CampoFormulario;
use App\Modules\Tenancy\Models\Formulario;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\RespuestaFormulario;
use App\Modules\Tenancy\TipoCampo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Respuestas a formularios dinámicos. Valida DINÁMICAMENTE contra la definición de
 * campos (obligatorios y opciones de selección) y guarda una respuesta por persona
 * (upsert). Tenant-local: opera sobre la BD del estudio resuelto.
 */
class RespuestasFormularioController
{
    private const LIMITE = 200;

    public function index(Request $request): JsonResponse
    {
        $formulario = Formulario::query()->where('ulid', (string) $request->route('formulario'))->firstOrFail();

        $respuestas = RespuestaFormulario::query()
            ->with('persona')
            ->where('formulario_id', $formulario->id)
            ->orderByDesc('id')
            ->limit(self::LIMITE)
            ->get();

        return response()->json([
            'data' => $respuestas->map(static fn (RespuestaFormulario $respuesta): array => [
                'id' => $respuesta->ulid,
                'persona' => $respuesta->persona !== null
                    ? trim($respuesta->persona->nombre.' '.($respuesta->persona->apellidos ?? ''))
                    : null,
                'valores' => $respuesta->valores,
            ])->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $formulario = Formulario::query()->with('campos')->where('ulid', (string) $request->route('formulario'))->firstOrFail();

        $persona = PersonaTenant::query()->where('ulid', (string) $request->input('persona_id'))->firstOrFail();

        /** @var array<string, mixed> $entrada */
        $entrada = is_array($request->input('valores')) ? $request->input('valores') : [];

        $valores = $this->validarYNormalizar($formulario->campos, $entrada);

        $respuesta = RespuestaFormulario::query()->updateOrCreate(
            ['formulario_id' => $formulario->id, 'persona_id' => $persona->id],
            ['valores' => $valores],
        );

        return response()->json(['data' => ['id' => $respuesta->ulid, 'valores' => $respuesta->valores]], 201);
    }

    /**
     * Valida las respuestas contra la definición de campos y devuelve los valores
     * normalizados (solo campos conocidos).
     *
     * @param  Collection<int, CampoFormulario>  $campos
     * @param  array<string, mixed>  $entrada
     * @return array<string, mixed>
     */
    private function validarYNormalizar($campos, array $entrada): array
    {
        $valores = [];

        foreach ($campos as $campo) {
            $valor = $entrada[$campo->ulid] ?? null;

            if ($campo->obligatorio && ($valor === null || $valor === '')) {
                throw ValidationException::withMessages([
                    $campo->ulid => ["El campo '{$campo->etiqueta}' es obligatorio."],
                ]);
            }

            if ($valor === null || $valor === '') {
                continue;
            }

            if ($campo->tipo === TipoCampo::Seleccion && ! in_array($valor, $campo->opciones ?? [], true)) {
                throw ValidationException::withMessages([
                    $campo->ulid => ["Valor inválido para '{$campo->etiqueta}'."],
                ]);
            }

            if ($campo->tipo === TipoCampo::Numero && ! is_numeric($valor)) {
                throw ValidationException::withMessages([
                    $campo->ulid => ["'{$campo->etiqueta}' debe ser numérico."],
                ]);
            }

            $valores[$campo->ulid] = $valor;
        }

        return $valores;
    }
}
