<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Asignación de personal a una sesión (instructor o asistente). Habilita la
// visibilidad de agenda del instructor (sus sesiones asignadas).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_sesion', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sesion_id')->constrained('sesiones')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->string('rol')->default('instructor');
            $table->timestamps();

            $table->unique(['sesion_id', 'persona_id']);
            $table->index('persona_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_sesion');
    }
};
