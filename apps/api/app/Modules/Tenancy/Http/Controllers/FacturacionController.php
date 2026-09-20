<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Application\PoliticaAlumnosActivos;
use App\Modules\Tenancy\Models\Estudio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estado de facturación SaaS del estudio: plan, precio por alumno, estado de la
 * suscripción, trial y uso del periodo. Es facturación de TurnoUno (control plane),
 * SEPARADA de los pagos que los alumnos hacen al estudio (esos viven en la BD del
 * tenant con las pasarelas del propio estudio). El conteo se hace en vivo sobre la
 * BD del tenant ya resuelta; aquí no se cobra nada.
 */
class FacturacionController
{
    public function __construct(private readonly PoliticaAlumnosActivos $politica) {}

    public function show(Request $request): JsonResponse
    {
        $estudio = $request->attributes->get('estudio');
        abort_unless($estudio instanceof Estudio, 404);

        $periodo = now($estudio->zona_horaria)->format('Y-m');
        $activos = $this->politica->contar($periodo); // conexión tenant ya activa
        // El cargo depende del modo de cobro (por activos o cuota fija mensual).
        $cargo = $estudio->cargoDelPeriodo($activos);

        return response()->json(['data' => [
            'plan' => $estudio->plan,
            'estado_facturacion' => $estudio->estado_facturacion->value,
            'trial_termina_en' => $estudio->trial_termina_en?->toDateString(),
            'modo_cobro' => $estudio->modo_cobro->value,
            'precio_por_alumno_minor' => $estudio->precio_por_alumno_minor,
            'cuota_fija_minor' => $estudio->cuota_fija_minor,
            'moneda' => $estudio->moneda,
            'uso' => [
                'periodo' => $periodo,
                'alumnos_activos' => $activos,
                'regla' => $this->politica->version(),
                'cargo_estimado_minor' => $cargo,
            ],
        ]]);
    }
}
