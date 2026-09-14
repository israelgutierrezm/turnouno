<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Configuración de una pasarela por tenant: si está activa, el modo (test/live) y
// sus credenciales. Las credenciales se guardan CIFRADAS (cast `encrypted` en el
// modelo) y nunca se exponen en la API. Una fila por (tenant, proveedor).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones_pasarela', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('proveedor');
            $table->boolean('activa')->default(false);
            $table->string('modo')->default('test');
            $table->text('credenciales')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'proveedor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_pasarela');
    }
};
