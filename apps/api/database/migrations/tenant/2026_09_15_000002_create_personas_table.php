<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): personas operativas del estudio (miembros/alumnos e
 * instructores). Base para el alta de alumno y la medición de alumnos activos.
 * `es_facturable`/`archivado` permiten excluir cuentas del conteo de facturación.
 * Vive en la BD del tenant, aislada del resto de estudios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('personas', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('nombre');
            $tabla->string('apellidos')->nullable();
            $tabla->string('email')->nullable();
            $tabla->string('tipo')->default('miembro')->index(); // miembro | instructor | staff
            $tabla->boolean('activo')->default(true);
            $tabla->boolean('es_facturable')->default(true); // false = excluido del conteo (demo/cortesía)
            $tabla->boolean('archivado')->default(false);
            $tabla->foreignId('usuario_id')->nullable(); // vínculo opcional con la cuenta de acceso tenant-local
            $tabla->timestamps();

            $tabla->index(['tipo', 'activo', 'es_facturable', 'archivado']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('personas');
    }
};
