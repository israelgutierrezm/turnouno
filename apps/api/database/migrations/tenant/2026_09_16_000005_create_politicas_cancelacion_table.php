<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): politica de cancelacion/no-show configurable (R8).
 * Una fila global (`actividad_id` NULL) y, opcionalmente, overrides por actividad.
 * La reserva CONGELA (snapshot) la politica al crearse, de modo que cambiarla luego
 * no altera reservas ya hechas. Sin `tenant_id`: aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('politicas_cancelacion', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            // NULL = politica global por defecto; con valor = override para esa actividad.
            $tabla->foreignId('actividad_id')->nullable()->constrained('actividades')->cascadeOnDelete();
            $tabla->unsignedInteger('horas_limite')->default(6);      // ventana para cancelar sin costo
            $tabla->boolean('penaliza_tarde')->default(true);         // cancelar tarde consume el credito
            $tabla->boolean('penaliza_no_show')->default(true);       // no-show consume el credito
            $tabla->unsignedInteger('tolerancia_no_show')->default(0); // no-shows tolerados antes de sancion (0 = sin strikes)
            $tabla->timestamps();

            $tabla->unique('actividad_id');
        });

        Schema::connection('tenant')->table('reservas', function (Blueprint $tabla): void {
            // Snapshot de la politica vigente al reservar.
            $tabla->unsignedInteger('horas_limite')->nullable()->after('unidades');
            $tabla->boolean('penaliza_tarde')->nullable()->after('horas_limite');
            $tabla->boolean('penaliza_no_show')->nullable()->after('penaliza_tarde');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('reservas', function (Blueprint $tabla): void {
            $tabla->dropColumn(['horas_limite', 'penaliza_tarde', 'penaliza_no_show']);
        });

        Schema::connection('tenant')->dropIfExists('politicas_cancelacion');
    }
};
