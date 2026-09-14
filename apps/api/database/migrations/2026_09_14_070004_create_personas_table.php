<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Personas: seres humanos del tenant. Distinto de `users` (cuentas de acceso).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nombre');
            $table->string('apellidos')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            // Un User se vincula a lo sumo con una Persona dentro de un tenant.
            $table->unique(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
