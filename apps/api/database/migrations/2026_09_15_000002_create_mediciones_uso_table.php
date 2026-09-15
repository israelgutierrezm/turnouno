<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control plane: historial de medición mensual de alumnos activos por estudio.
 * Guarda SOLO el agregado (cantidad) + la regla/versión usada y su evidencia; los
 * datos que la originan viven en la BD del tenant y nunca se copian aquí. Se
 * congela al cerrar el periodo y no cambia retroactivamente una factura emitida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mediciones_uso', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('estudio_id')->constrained('estudios')->cascadeOnDelete();
            $tabla->string('periodo', 7); // YYYY-MM
            $tabla->string('regla_version');
            $tabla->unsignedInteger('cantidad');
            $tabla->json('evidencia')->nullable(); // p. ej. reglas aplicadas / desglose
            $tabla->timestamp('calculada_en');
            $tabla->boolean('congelada')->default(false);
            $tabla->timestamps();

            $tabla->unique(['estudio_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediciones_uso');
    }
};
