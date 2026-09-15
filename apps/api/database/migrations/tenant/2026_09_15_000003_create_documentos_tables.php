<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): módulo de documentos. El administrador define qué
 * documentos requiere (`tipos_documento`) y cada persona (miembro/instructor)
 * tiene sus `documentos` cargados, que el staff valida (pendiente→aprobado/
 * rechazado). Todo aislado por tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('tipos_documento', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('nombre');
            $tabla->string('descripcion')->nullable();
            $tabla->boolean('obligatorio')->default(false);
            $tabla->string('aplica_a')->default('miembro'); // miembro | instructor | todos
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });

        Schema::connection('tenant')->create('documentos', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $tabla->foreignId('tipo_documento_id')->nullable()->constrained('tipos_documento')->nullOnDelete();
            $tabla->string('nombre');            // etiqueta legible
            $tabla->string('ruta');              // archivo en disco (tenant-namespaced)
            $tabla->string('mime')->nullable();
            $tabla->string('estado')->default('pendiente'); // pendiente | aprobado | rechazado
            $tabla->string('motivo')->nullable();           // motivo de rechazo
            $tabla->unsignedBigInteger('validado_por')->nullable(); // users.id tenant-local
            $tabla->timestamp('validado_en')->nullable();
            $tabla->timestamp('subido_en')->nullable();
            $tabla->timestamps();

            $tabla->index(['persona_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('documentos');
        Schema::connection('tenant')->dropIfExists('tipos_documento');
    }
};
