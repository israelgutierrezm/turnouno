<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): FAMILIAS (R26) — hogar (agrupa a la familia) y tutela
 * (tutor → dependiente, p. ej. madre responsable de un menor). Modela
 * comprador != participante y habilita que un tutor gestione a sus dependientes. Se
 * agrega `hogar_id` a `personas`. Sin `tenant_id`: aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('hogares', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('nombre');
            $tabla->timestamps();
        });

        // Tabla pivote (tutor<->dependiente): sin `ulid` publico; se adjunta via
        // belongsToMany (que inserta filas de pivote sin pasar por el modelo).
        Schema::connection('tenant')->create('tutelas', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('tutor_id')->constrained('personas')->cascadeOnDelete();
            $tabla->foreignId('dependiente_id')->constrained('personas')->cascadeOnDelete();
            $tabla->string('parentesco')->nullable();
            $tabla->timestamps();

            $tabla->unique(['tutor_id', 'dependiente_id']);
            $tabla->index('dependiente_id');
        });

        Schema::connection('tenant')->table('personas', function (Blueprint $tabla): void {
            // Sin FK en el ALTER (SQLite); aislamiento por base.
            $tabla->unsignedBigInteger('hogar_id')->nullable()->after('id');
            $tabla->index('hogar_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('personas', function (Blueprint $tabla): void {
            $tabla->dropIndex(['hogar_id']);
            $tabla->dropColumn('hogar_id');
        });

        Schema::connection('tenant')->dropIfExists('tutelas');
        Schema::connection('tenant')->dropIfExists('hogares');
    }
};
