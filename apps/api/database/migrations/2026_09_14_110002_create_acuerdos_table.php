<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Acuerdo: la compra de un producto comercial por una persona.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acuerdos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->foreignId('producto_comercial_id')->constrained('productos_comerciales')->cascadeOnDelete();
            $table->date('fecha_inicio');
            $table->string('estado')->default('activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acuerdos');
    }
};
