<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Orden de compra de una persona (comprador). El total se guarda como snapshot
// (suma de las líneas) en `total_minor` + `moneda`, nunca float.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->string('estado')->default('pendiente');
            $table->unsignedBigInteger('total_minor');
            $table->char('moneda', 3);
            $table->timestamps();

            $table->index(['persona_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes');
    }
};
