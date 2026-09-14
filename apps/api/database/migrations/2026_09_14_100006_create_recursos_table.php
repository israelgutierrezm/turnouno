<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Recurso: activo reservable o limitante de capacidad (Carril 1, Poste 3).
// Jerarquía Instalación → Recurso → Recurso hijo. Modo UNIDAD o POOL.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recursos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instalacion_id')->constrained('instalaciones')->cascadeOnDelete();
            $table->foreignId('recurso_padre_id')->nullable()->constrained('recursos')->nullOnDelete();
            $table->string('nombre');
            $table->string('tipo')->nullable();
            $table->string('modo')->default('unidad'); // unidad | pool
            $table->unsignedInteger('capacidad')->default(1);
            $table->string('estado')->default('activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recursos');
    }
};
