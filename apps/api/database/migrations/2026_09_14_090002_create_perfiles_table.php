<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Perfil: un rol que ostenta una persona (Miembro, Instructor, ...).
// Una persona puede tener varios perfiles simultáneamente.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfiles', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->string('tipo');
            $table->timestamps();

            $table->unique(['persona_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfiles');
    }
};
