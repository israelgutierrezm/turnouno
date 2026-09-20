<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): CRM comercial (R15). Un prospecto (lead) es un cliente
 * potencial en el embudo, con su canal de origen, etapa, responsable y seguimiento.
 * Al ganarlo se convierte en `persona` (miembro) y se guarda el enlace. Cada
 * interacción queda en la bitácora `prospecto_actividades`.
 *
 * - `prospectos.persona_id`: null hasta convertir; luego apunta al miembro creado.
 * - `prospectos.responsable_id`: usuario tenant-local a cargo del seguimiento (null = sin asignar).
 * - Dinero/impuestos no aplican aquí; el valor comercial se materializa al vender.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('prospectos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->unsignedBigInteger('persona_id')->nullable();
            $tabla->unsignedBigInteger('responsable_id')->nullable();
            $tabla->unsignedBigInteger('sucursal_id')->nullable();
            $tabla->string('nombre');
            $tabla->string('email')->nullable();
            $tabla->string('telefono')->nullable();
            $tabla->string('origen')->default('otro');
            $tabla->string('etapa')->default('nuevo');
            $tabla->string('interes')->nullable();
            $tabla->string('motivo')->nullable();
            $tabla->date('proximo_seguimiento')->nullable();
            $tabla->timestamp('convertido_en')->nullable();
            $tabla->timestamps();

            $tabla->index('etapa');
            $tabla->index('responsable_id');
            $tabla->index('persona_id');
        });

        Schema::connection('tenant')->create('prospecto_actividades', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->unsignedBigInteger('prospecto_id');
            $tabla->unsignedBigInteger('usuario_id')->nullable();
            $tabla->string('tipo');
            $tabla->text('detalle')->nullable();
            $tabla->timestamps();

            $tabla->index('prospecto_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('prospecto_actividades');
        Schema::connection('tenant')->dropIfExists('prospectos');
    }
};
