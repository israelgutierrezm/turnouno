<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): guarda el costo en creditos (unidades escaladas) con
 * el que se creo la reserva, para que la promocion desde lista de espera consuma el
 * costo REAL de la sesion y no un valor fijo. Por defecto 1 credito = 1000 unidades.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('reservas', function (Blueprint $tabla): void {
            $tabla->unsignedInteger('costo_unidades')->default(1000)->after('unidades');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('reservas', function (Blueprint $tabla): void {
            $tabla->dropColumn('costo_unidades');
        });
    }
};
