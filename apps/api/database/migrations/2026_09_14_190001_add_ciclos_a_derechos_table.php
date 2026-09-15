<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ciclos y restricciones de un derecho: política de reset/rollover, cupo por
// ciclo, ventana del ciclo actual, y restricciones opcionales por actividad y
// sucursal (ver MEMBERSHIP_ENGINE.md).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('derechos', function (Blueprint $table): void {
            $table->foreignId('actividad_id')->nullable()->after('ambito')->constrained('actividades')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->after('actividad_id')->constrained('sucursales')->nullOnDelete();
            $table->string('politica_reset')->default('ninguno')->after('ilimitado');
            $table->unsignedBigInteger('unidades_por_ciclo')->nullable()->after('politica_reset');
            $table->string('politica_rollover')->default('ninguno')->after('unidades_por_ciclo');
            $table->unsignedBigInteger('rollover_max')->nullable()->after('politica_rollover');
            $table->date('ciclo_inicio')->nullable()->after('rollover_max');
            $table->date('ciclo_fin')->nullable()->after('ciclo_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('derechos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('actividad_id');
            $table->dropConstrainedForeignId('sucursal_id');
            $table->dropColumn(['politica_reset', 'unidades_por_ciclo', 'politica_rollover', 'rollover_max', 'ciclo_inicio', 'ciclo_fin']);
        });
    }
};
