<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lealtad (R24): programa de puntos por tenant. Los miembros ACUMULAN puntos (al asistir
 * o comprar) y los CANJEAN por recompensas. El saldo es la SUMA de los deltas del ledger
 * `movimientos_puntos` (nunca se guarda), auditable como el ledger de créditos/inventario.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Configuración del programa (una sola fila por tenant): reglas de acumulación.
        Schema::connection('tenant')->create('programa_lealtad', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->boolean('activa')->default(false);
            $tabla->unsignedInteger('puntos_por_asistencia')->default(0);
            $tabla->unsignedInteger('puntos_por_moneda')->default(0); // puntos por cada 1.00 de la moneda gastada
            $tabla->timestamps();
        });

        // Catálogo de recompensas canjeables.
        Schema::connection('tenant')->create('recompensas_lealtad', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('nombre');
            $tabla->string('descripcion')->nullable();
            $tabla->unsignedInteger('costo_puntos');
            $tabla->boolean('activa')->default(true);
            $tabla->timestamps();
        });

        // Ledger de puntos (delta con signo; saldo = SUMA(puntos)).
        Schema::connection('tenant')->create('movimientos_puntos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->unsignedBigInteger('persona_id')->index();
            $tabla->string('tipo');   // acumulacion | canje | ajuste | reverso
            $tabla->string('origen'); // asistencia | compra | manual | canje
            $tabla->integer('puntos'); // delta con signo
            $tabla->integer('saldo_posterior'); // snapshot para conciliación
            $tabla->string('descripcion')->nullable();
            $tabla->string('referencia_tipo')->nullable();
            $tabla->string('referencia_id')->nullable();
            $tabla->unsignedBigInteger('recompensa_id')->nullable();
            $tabla->unsignedBigInteger('actor_id')->nullable();
            $tabla->string('actor_nombre')->nullable();
            // Idempotencia de la acumulación por evento del outbox (un evento = un movimiento).
            $tabla->ulid('evento_ulid')->nullable()->unique();
            $tabla->json('metadata')->nullable();
            $tabla->timestamps();
        });

        // Canjes de recompensa (ciclo de entrega; los puntos ya se descontaron en el ledger).
        Schema::connection('tenant')->create('canjes_lealtad', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->unsignedBigInteger('persona_id')->index();
            $tabla->unsignedBigInteger('recompensa_id');
            $tabla->string('recompensa_nombre'); // snapshot
            $tabla->unsignedInteger('puntos');
            $tabla->string('estado')->default('pendiente')->index();
            $tabla->timestamp('entregado_en')->nullable();
            $tabla->unsignedBigInteger('actor_id')->nullable();
            $tabla->string('actor_nombre')->nullable();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('canjes_lealtad');
        Schema::connection('tenant')->dropIfExists('movimientos_puntos');
        Schema::connection('tenant')->dropIfExists('recompensas_lealtad');
        Schema::connection('tenant')->dropIfExists('programa_lealtad');
    }
};
