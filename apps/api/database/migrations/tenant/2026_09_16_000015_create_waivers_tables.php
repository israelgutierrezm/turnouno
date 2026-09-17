<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): WAIVERS / consentimientos versionados (R27). Cada
 * publicacion de una `clave` (terminos, consentimiento…) crea una nueva VERSION con su
 * `hash`; la aceptacion de una persona queda sellada (aceptado_en/ip/hash de la
 * version aceptada). Al publicar una version nueva, la persona debe RE-aceptar (su
 * aceptacion previa es de otra version). Sin `tenant_id`: aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('waivers', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('clave');        // terminos | consentimiento_medico | ...
            $tabla->string('titulo');
            $tabla->text('contenido');
            $tabla->unsignedInteger('version');
            $tabla->string('hash');         // sha256 del contenido de esa version
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();

            $tabla->unique(['clave', 'version']);
        });

        Schema::connection('tenant')->create('aceptaciones_waiver', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $tabla->foreignId('waiver_id')->constrained('waivers')->cascadeOnDelete();
            $tabla->dateTime('aceptado_en');
            $tabla->string('ip')->nullable();
            $tabla->string('hash'); // hash de la version aceptada (evidencia)
            $tabla->timestamps();

            $tabla->unique(['persona_id', 'waiver_id']);
            $tabla->index('persona_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('aceptaciones_waiver');
        Schema::connection('tenant')->dropIfExists('waivers');
    }
};
