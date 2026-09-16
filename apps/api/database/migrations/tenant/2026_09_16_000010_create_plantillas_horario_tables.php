<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): RECURRENCIA de agenda (R5). Una `plantilla_horario`
 * define una oferta impartida en una sucursal ciertos dias de la semana a una hora
 * local; de ahi se MATERIALIZAN sesiones (con `serie_id` apuntando a la plantilla).
 * `excepciones_horario` son fechas sin generacion (feriados/cierres). La generacion es
 * idempotente por (serie_id, inicia_en); una instancia editada/cancelada NO se
 * regenera (override por instancia). Sin `tenant_id`: aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('plantillas_horario', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('oferta_id')->constrained('ofertas')->cascadeOnDelete();
            $tabla->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $tabla->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $tabla->json('dias_semana');            // ISO 1 (lun) .. 7 (dom)
            $tabla->string('hora_local');           // HH:MM en la zona de la sucursal
            $tabla->unsignedInteger('duracion_minutos');
            $tabla->unsignedInteger('capacidad')->nullable();
            $tabla->boolean('activo')->default(true);
            $tabla->date('vigente_desde');
            $tabla->date('vigente_hasta')->nullable();
            $tabla->timestamps();
        });

        Schema::connection('tenant')->create('excepciones_horario', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->date('fecha')->unique();
            $tabla->string('motivo')->nullable();
            $tabla->timestamps();
        });

        Schema::connection('tenant')->table('sesiones', function (Blueprint $tabla): void {
            // Serie de la que fue materializada (NULL = sesion suelta/manual). Sin FK en
            // el ALTER (SQLite no la agrega asi); el aislamiento ya es por base.
            $tabla->unsignedBigInteger('serie_id')->nullable()->after('sucursal_id');
            // Idempotencia de la generacion: no duplica una instancia de la serie.
            $tabla->unique(['serie_id', 'inicia_en']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('sesiones', function (Blueprint $tabla): void {
            $tabla->dropUnique(['serie_id', 'inicia_en']);
            $tabla->dropColumn('serie_id');
        });

        Schema::connection('tenant')->dropIfExists('excepciones_horario');
        Schema::connection('tenant')->dropIfExists('plantillas_horario');
    }
};
