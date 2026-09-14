<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Asistencia: check-in de una reserva confirmada (presente/ausente). Una por
// reserva (unique) para poder re-marcar sin duplicar.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reserva_id')->unique()->constrained('reservas')->cascadeOnDelete();
            $table->string('estado');
            $table->dateTime('registrada_en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
