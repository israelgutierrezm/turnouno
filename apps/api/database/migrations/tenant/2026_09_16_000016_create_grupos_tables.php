<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): GRUPOS / cursos con inscripcion (R25), para
 * natacion/danza/academias. Un grupo sigue una `plantilla_horario` (serie); al
 * inscribir a una persona se le auto-reservan las ocurrencias futuras de esa serie
 * (la asistencia se marca por ocurrencia con el flujo existente). Sin `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('grupos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('nombre');
            $tabla->foreignId('plantilla_id')->constrained('plantillas_horario')->cascadeOnDelete();
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });

        Schema::connection('tenant')->create('inscripciones_grupo', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $tabla->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();

            $tabla->unique(['grupo_id', 'persona_id']);
            $tabla->index('persona_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('inscripciones_grupo');
        Schema::connection('tenant')->dropIfExists('grupos');
    }
};
