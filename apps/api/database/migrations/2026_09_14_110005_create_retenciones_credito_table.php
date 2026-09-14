<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Retención (hold) de créditos: reserva unidades de un derecho sin consumirlas
// aún (para reservas de clase concurrency-safe). disponible = saldo - holds activos.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retenciones_credito', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('derecho_id')->constrained('derechos')->cascadeOnDelete();
            $table->unsignedBigInteger('unidades');
            $table->string('estado')->default('activa');
            $table->string('descripcion')->nullable();
            $table->timestamps();

            $table->index('derecho_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retenciones_credito');
    }
};
