<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tutela: relación tutor → dependiente (p. ej. madre responsable de un menor).
// Modela comprador != participante.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutelas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tutor_id')->constrained('personas')->cascadeOnDelete();
            $table->foreignId('dependiente_id')->constrained('personas')->cascadeOnDelete();
            $table->string('parentesco')->nullable();
            $table->timestamps();

            $table->unique(['tutor_id', 'dependiente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutelas');
    }
};
