<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sucursal: unidad operativa dentro del tenant. No es frontera de seguridad.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('slug');
            $table->string('zona_horaria')->nullable();
            $table->string('estado')->default('activa');
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
