<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Application;

use App\Modules\Creditos\LibroMayor;
use App\Modules\Creditos\TipoMovimiento;
use App\Modules\Membresias\Models\Acuerdo;
use App\Modules\Membresias\Models\ProductoComercial;
use App\Modules\Personas\Models\Persona;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registra la compra de un producto: crea el acuerdo, otorga el derecho y, si el
 * producto es limitado, concede sus créditos en el ledger. Todo en una sola
 * transacción para mantener la consistencia (ver ADR-0004 y MEMBERSHIP_ENGINE).
 */
class CrearAcuerdo
{
    public function __construct(private readonly LibroMayor $libro) {}

    public function ejecutar(Persona $persona, ProductoComercial $producto, ?string $fechaInicio = null): Acuerdo
    {
        return DB::transaction(function () use ($persona, $producto, $fechaInicio): Acuerdo {
            $acuerdo = Acuerdo::create([
                'persona_id' => $persona->id,
                'producto_comercial_id' => $producto->id,
                'fecha_inicio' => $fechaInicio ?? Carbon::now()->toDateString(),
                'estado' => 'activo',
            ]);

            $derecho = $acuerdo->derechos()->create([
                'ambito' => 'general',
                'ilimitado' => $producto->ilimitado,
            ]);

            $creditos = $producto->creditos_incluidos;
            if (! $producto->ilimitado && $creditos !== null && $creditos > 0) {
                $this->libro->registrar($derecho, TipoMovimiento::Concesion, $creditos, 'Concesión inicial');
            }

            return $acuerdo;
        });
    }
}
