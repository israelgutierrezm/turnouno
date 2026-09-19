<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): proceso de morosidad (dunning, R10) de una membresía
 * cuyo cobro falló. Registra el periodo de gracia, los reintentos y la suspensión.
 * Un acuerdo tiene a lo sumo un proceso ABIERTO a la vez (se controla en la app).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('procesos_dunning', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            // SQLite (dev/test) no permite FK vía ALTER; aquí es create, pero se mantiene
            // el estilo del data plane: columna + índice, sin constraint.
            $tabla->unsignedBigInteger('acuerdo_id')->index();
            $tabla->string('estado')->default('en_mora');
            $tabla->unsignedInteger('intentos')->default(1);
            $tabla->timestamp('gracia_hasta');
            $tabla->timestamp('proximo_intento_en')->nullable();
            $tabla->string('ultimo_motivo')->nullable();
            $tabla->timestamp('suspendido_en')->nullable();
            $tabla->timestamp('regularizado_en')->nullable();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('procesos_dunning');
    }
};
