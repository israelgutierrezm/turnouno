<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): capacidad por canal / marketplace (R20). Cada reserva
 * queda etiquetada con su canal (booking_source) y una regla puede reservar N cupos de
 * una oferta para un canal, liberándolos al pool general `liberar_horas_antes` del
 * inicio (liberación progresiva). El invariante de no-sobreventa se mantiene: la regla
 * sólo aparta cupos que aún no se usaron.
 *
 * - `reservas.canal`: canal por el que entró la reserva (default 'directo').
 * - `reglas_capacidad_canal.cupos`: asientos reservados para el canal en cada sesión de
 *   la oferta.
 * - `reglas_capacidad_canal.liberar_horas_antes`: horas antes del inicio en que los
 *   cupos no usados vuelven al pool general (0 = nunca liberar antes del inicio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('reservas', function (Blueprint $tabla): void {
            $tabla->string('canal')->default('directo')->after('estado');
            $tabla->index('canal');
        });

        Schema::connection('tenant')->create('reglas_capacidad_canal', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->unsignedBigInteger('oferta_id');
            $tabla->string('canal');
            $tabla->unsignedInteger('cupos');
            $tabla->unsignedInteger('liberar_horas_antes')->default(0);
            $tabla->boolean('activa')->default(true);
            $tabla->timestamps();

            $tabla->unique(['oferta_id', 'canal']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('reglas_capacidad_canal');

        Schema::connection('tenant')->table('reservas', function (Blueprint $tabla): void {
            $tabla->dropIndex(['canal']);
            $tabla->dropColumn('canal');
        });
    }
};
