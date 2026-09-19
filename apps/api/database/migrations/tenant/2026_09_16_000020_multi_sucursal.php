<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): multi-sucursal (R18). La sucursal es una unidad de
 * negocio con su propia moneda e impuesto, opcionalmente agrupada por región; y cada
 * persona puede tener una sucursal "de casa" (home) para segmentar y reportar.
 *
 * - `sucursales.region`: agrupador libre por encima de la sucursal (para reportes).
 * - `sucursales.moneda`: ISO-4217 de la sucursal (null = hereda la del producto).
 * - `sucursales.impuesto_tasa_bps`: impuesto en puntos base (1600 = 16%). Entero,
 *   nunca float (dinero).
 * - `personas.sucursal_id`: sucursal de casa (home) de la persona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('sucursales', function (Blueprint $tabla): void {
            $tabla->string('region')->nullable()->after('nombre');
            $tabla->char('moneda', 3)->nullable()->after('region');
            $tabla->unsignedInteger('impuesto_tasa_bps')->default(0)->after('moneda');
        });

        // SQLite (dev/test) no permite FK vía ALTER: columna simple + índice.
        Schema::connection('tenant')->table('personas', function (Blueprint $tabla): void {
            $tabla->unsignedBigInteger('sucursal_id')->nullable()->after('hogar_id');
            $tabla->index('sucursal_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('personas', function (Blueprint $tabla): void {
            $tabla->dropIndex(['sucursal_id']);
            $tabla->dropColumn('sucursal_id');
        });

        Schema::connection('tenant')->table('sucursales', function (Blueprint $tabla): void {
            $tabla->dropColumn(['region', 'moneda', 'impuesto_tasa_bps']);
        });
    }
};
