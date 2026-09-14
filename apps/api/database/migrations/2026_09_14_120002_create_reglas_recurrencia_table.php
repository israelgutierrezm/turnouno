<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Regla de recurrencia de una plantilla: día de la semana (ISO 1-7) + hora local
// de inicio. Una clase que corre lunes/miércoles son dos reglas de una plantilla.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglas_recurrencia', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plantilla_horario_id')->constrained('plantillas_horario')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');
            $table->timestamps();

            $table->unique(['plantilla_horario_id', 'dia_semana', 'hora_inicio'], 'reglas_recurrencia_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglas_recurrencia');
    }
};
