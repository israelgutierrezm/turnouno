<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// El producto define la plantilla de ciclo/rollover/restricciones que hereda el
// derecho al venderse. Nulo/`ninguno` mantiene el comportamiento de pack simple.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos_comerciales', function (Blueprint $table): void {
            $table->foreignId('actividad_id')->nullable()->after('creditos_incluidos')->constrained('actividades')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->after('actividad_id')->constrained('sucursales')->nullOnDelete();
            $table->string('politica_reset')->default('ninguno')->after('sucursal_id');
            $table->unsignedBigInteger('unidades_por_ciclo')->nullable()->after('politica_reset');
            $table->string('politica_rollover')->default('ninguno')->after('unidades_por_ciclo');
            $table->unsignedBigInteger('rollover_max')->nullable()->after('politica_rollover');
        });
    }

    public function down(): void
    {
        Schema::table('productos_comerciales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('actividad_id');
            $table->dropConstrainedForeignId('sucursal_id');
            $table->dropColumn(['politica_reset', 'unidades_por_ciclo', 'politica_rollover', 'rollover_max']);
        });
    }
};
