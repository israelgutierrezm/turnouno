<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): formularios dinámicos. El administrador define
 * formularios con sus campos para solicitar información a miembros/instructores;
 * cada persona tiene una respuesta con los valores capturados. Todo aislado por
 * tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('formularios', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('nombre');
            $tabla->string('descripcion')->nullable();
            $tabla->string('aplica_a')->default('miembro'); // miembro | instructor | todos
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });

        Schema::connection('tenant')->create('campos_formulario', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('formulario_id')->constrained('formularios')->cascadeOnDelete();
            $tabla->string('etiqueta');
            $tabla->string('tipo')->default('texto'); // texto|textarea|numero|fecha|booleano|seleccion
            $tabla->boolean('obligatorio')->default(false);
            $tabla->json('opciones')->nullable(); // para 'seleccion'
            $tabla->unsignedInteger('orden')->default(0);
            $tabla->timestamps();
        });

        Schema::connection('tenant')->create('respuestas_formulario', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('formulario_id')->constrained('formularios')->cascadeOnDelete();
            $tabla->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $tabla->json('valores'); // { campo_ulid: valor }
            $tabla->timestamps();

            $tabla->unique(['formulario_id', 'persona_id']); // una respuesta por persona/formulario
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('respuestas_formulario');
        Schema::connection('tenant')->dropIfExists('campos_formulario');
        Schema::connection('tenant')->dropIfExists('formularios');
    }
};
