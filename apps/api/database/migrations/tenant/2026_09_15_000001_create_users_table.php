<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): identidad tenant-local. El email es único SOLO
 * dentro de esta base, por lo que el mismo correo puede existir en otro tenant
 * como una cuenta totalmente distinta (IDs, contraseña y recuperación propias).
 * Preparada para SSO de Google (`google_id`) y activación de cuenta (`activo`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('users', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->string('name');
            $tabla->string('email');
            $tabla->timestamp('email_verified_at')->nullable();
            $tabla->string('password')->nullable(); // nulo hasta activar la cuenta
            $tabla->string('google_id')->nullable();
            $tabla->boolean('activo')->default(false);
            $tabla->string('activation_token')->nullable(); // hash del token de activación de un solo uso
            $tabla->rememberToken();
            $tabla->timestamps();

            // Único dentro de la BD del tenant = único por tenant (requisito).
            $tabla->unique('email');
            $tabla->unique('google_id');
        });

        Schema::connection('tenant')->create('password_reset_tokens', function (Blueprint $tabla): void {
            $tabla->string('email')->primary();
            $tabla->string('token');
            $tabla->timestamp('created_at')->nullable();
        });

        Schema::connection('tenant')->create('personal_access_tokens', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->morphs('tokenable');
            $tabla->string('name');
            $tabla->string('token', 64)->unique();
            $tabla->text('abilities')->nullable();
            $tabla->timestamp('last_used_at')->nullable();
            $tabla->timestamp('expires_at')->nullable();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('personal_access_tokens');
        Schema::connection('tenant')->dropIfExists('password_reset_tokens');
        Schema::connection('tenant')->dropIfExists('users');
    }
};
