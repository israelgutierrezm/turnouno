<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ledger de créditos: fuente de verdad auditable del saldo de un derecho.
// `unidades` es un entero CON SIGNO (+ concesión, - consumo). Nunca float.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_credito', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('derecho_id')->constrained('derechos')->cascadeOnDelete();
            $table->string('tipo');
            $table->bigInteger('unidades');
            $table->string('descripcion')->nullable();
            $table->timestamps();

            $table->index(['derecho_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_credito');
    }
};
