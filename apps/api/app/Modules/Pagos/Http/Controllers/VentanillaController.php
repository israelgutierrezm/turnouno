<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Http\Controllers;

use App\Modules\Pagos\Application\AprobarVentanilla;
use App\Modules\Pagos\Application\RechazarVentanilla;
use App\Modules\Pagos\EstadoPago;
use App\Modules\Pagos\Exceptions\PagoNoEsVentanilla;
use App\Modules\Pagos\Http\Requests\SubirComprobanteRequest;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\ProveedorPasarela;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pago por ventanilla (depósito con comprobante). El miembro dueño de la orden
 * sube su comprobante; el staff (pagos.crear) lo revisa y aprueba o rechaza.
 */
class VentanillaController
{
    public function subir(SubirComprobanteRequest $request, Pago $pago): JsonResponse
    {
        $this->autorizarAcceso($pago);
        $this->exigirVentanillaPendiente($pago);

        $archivo = $request->file('comprobante');
        $ruta = $archivo->storeAs(
            "comprobantes/{$pago->tenant_id}",
            $pago->ulid.'.'.$archivo->getClientOriginalExtension(),
            'local',
        );

        $pago->update([
            'comprobante_ruta' => $ruta,
            'comprobante_subido_en' => now(),
        ]);

        return response()->json(['data' => ['id' => $pago->ulid, 'comprobante' => true]], 201);
    }

    public function ver(Pago $pago): StreamedResponse
    {
        $this->autorizarAcceso($pago);
        abort_if($pago->comprobante_ruta === null, 404);

        return Storage::disk('local')->download($pago->comprobante_ruta);
    }

    public function aprobar(Pago $pago, AprobarVentanilla $aprobar): JsonResponse
    {
        Gate::authorize('pagos.crear');

        $aprobar->ejecutar($pago);
        $pago->refresh()->loadMissing('orden');

        return response()->json([
            'data' => [
                'id' => $pago->ulid,
                'estado' => $pago->estado->value,
                'orden_estado' => $pago->orden->estado->value,
            ],
        ]);
    }

    public function rechazar(Pago $pago, RechazarVentanilla $rechazar): JsonResponse
    {
        Gate::authorize('pagos.crear');

        $rechazar->ejecutar($pago);

        return response()->json(['data' => ['id' => $pago->ulid, 'estado' => $pago->refresh()->estado->value]]);
    }

    /**
     * Acceso al comprobante: el staff con `pagos.crear`, o el miembro dueño de la orden.
     */
    private function autorizarAcceso(Pago $pago): void
    {
        if (Gate::allows('pagos.crear')) {
            return;
        }

        $usuario = request()->user();
        $pago->loadMissing('orden.persona');
        $duenoId = $pago->orden->persona->user_id;

        abort_unless(
            $usuario !== null && $duenoId !== null && (int) $duenoId === (int) $usuario->getAuthIdentifier(),
            403,
        );
    }

    private function exigirVentanillaPendiente(Pago $pago): void
    {
        if ($pago->proveedor !== ProveedorPasarela::Ventanilla->value || $pago->estado !== EstadoPago::Pendiente) {
            throw new PagoNoEsVentanilla('No es un pago de ventanilla pendiente.');
        }
    }
}
