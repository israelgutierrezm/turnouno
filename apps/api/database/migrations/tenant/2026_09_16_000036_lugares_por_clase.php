<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): mapa de lugares por clase (R4). Una oferta puede tener N
 * lugares numerados (reformers de pilates, bicis de spinning…); al reservar, el alumno
 * elige su lugar y no puede repetirse en la misma sesión.
 *
 * - `ofertas.lugares`: número de lugares numerados (0 = sin lugares asignados).
 * - `reservas.lugar`: lugar elegido (1..N), null si la clase no asigna lugares.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('ofertas', function (Blueprint $tabla): void {
            $tabla->unsignedInteger('lugares')->default(0)->after('capacidad');
        });

        Schema::connection('tenant')->table('reservas', function (Blueprint $tabla): void {
            $tabla->unsignedInteger('lugar')->nullable()->after('canal');
            $tabla->index(['sesion_id', 'lugar']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('reservas', function (Blueprint $tabla): void {
            $tabla->dropIndex(['sesion_id', 'lugar']);
            $tabla->dropColumn('lugar');
        });

        Schema::connection('tenant')->table('ofertas', function (Blueprint $tabla): void {
            $tabla->dropColumn('lugares');
        });
    }
};
