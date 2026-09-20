<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): programa de referidos (R23). Cada miembro tiene un código
 * para invitar; un prospecto creado con ese código queda atribuido a quien refirió y,
 * al convertirse en miembro, se genera un cupón de recompensa (reusa Promociones/R22).
 *
 * - `codigos_referido`: un código único por persona (referidor).
 * - `referidos`: la atribución (referidor + prospecto + persona convertida) y su cupón.
 * - `programa_referidos`: config del premio (una fila): tipo/valor del cupón y vigencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('codigos_referido', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->unsignedBigInteger('persona_id');
            $tabla->string('codigo');
            $tabla->timestamps();

            $tabla->unique('persona_id');
            $tabla->unique('codigo');
        });

        Schema::connection('tenant')->create('referidos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->unsignedBigInteger('referidor_id');
            $tabla->string('codigo');
            $tabla->unsignedBigInteger('prospecto_id')->nullable();
            $tabla->unsignedBigInteger('persona_referida_id')->nullable();
            $tabla->string('estado')->default('pendiente');
            $tabla->unsignedBigInteger('recompensa_promocion_id')->nullable();
            $tabla->timestamp('convertido_en')->nullable();
            $tabla->timestamps();

            $tabla->index('referidor_id');
            $tabla->index('estado');
        });

        Schema::connection('tenant')->create('programa_referidos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('recompensa_tipo')->default('monto_fijo');
            $tabla->unsignedBigInteger('recompensa_valor')->default(10000);
            $tabla->unsignedInteger('vigencia_dias')->default(90);
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('programa_referidos');
        Schema::connection('tenant')->dropIfExists('referidos');
        Schema::connection('tenant')->dropIfExists('codigos_referido');
    }
};
