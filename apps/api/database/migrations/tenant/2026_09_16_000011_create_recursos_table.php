<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): RECURSOS reservables (R3) — salas, canchas, carriles,
 * equipos. Una sesion puede consumir un recurso; el motor impide sobre-reservarlo
 * (modo `unidad` = 1 sesion a la vez; `pool` = hasta `capacidad` simultaneas). Se
 * agrega `recurso_id` a `sesiones` y a `plantillas_horario`. Sin FK en los ALTER
 * (SQLite); aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('recursos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $tabla->string('nombre');
            $tabla->string('tipo')->nullable();   // sala | cancha | carril | equipo | poste...
            $tabla->string('modo')->default('unidad'); // unidad | pool
            $tabla->unsignedInteger('capacidad')->default(1);
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();

            $tabla->index('sucursal_id');
        });

        Schema::connection('tenant')->table('sesiones', function (Blueprint $tabla): void {
            $tabla->unsignedBigInteger('recurso_id')->nullable()->after('serie_id');
            $tabla->index(['recurso_id', 'inicia_en']);
        });

        Schema::connection('tenant')->table('plantillas_horario', function (Blueprint $tabla): void {
            $tabla->unsignedBigInteger('recurso_id')->nullable()->after('instructor_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('plantillas_horario', function (Blueprint $tabla): void {
            $tabla->dropColumn('recurso_id');
        });

        Schema::connection('tenant')->table('sesiones', function (Blueprint $tabla): void {
            $tabla->dropIndex(['recurso_id', 'inicia_en']);
            $tabla->dropColumn('recurso_id');
        });

        Schema::connection('tenant')->dropIfExists('recursos');
    }
};
