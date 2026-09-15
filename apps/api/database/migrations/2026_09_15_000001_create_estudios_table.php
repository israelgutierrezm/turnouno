<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control plane: registro central de estudios (tenants SaaS). Contiene SOLO lo
 * necesario para operar el SaaS (slug, dominio, estado, trial, plan, facturación,
 * config de la BD del tenant y contacto). NUNCA datos operativos del estudio
 * (usuarios/alumnos/reservas/pagos): esos viven en la BD del propio tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudios', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();

            // Identidad pública del estudio.
            $tabla->string('nombre');
            $tabla->string('slug')->unique();
            $tabla->string('logo_url')->nullable();

            // Ciclo de vida del aprovisionamiento y del SaaS.
            $tabla->string('estado')->default('provisioning')->index();
            $tabla->string('paso_aprovisionamiento')->nullable(); // reanudable
            $tabla->timestamp('aprovisionado_en')->nullable();

            // Directorio público.
            $tabla->boolean('publicado')->default(false)->index();
            $tabla->boolean('privado')->default(false);
            $tabla->string('pais', 2)->nullable();
            $tabla->string('ciudad')->nullable();
            $tabla->string('zona_horaria')->default('America/Mexico_City');

            // Contacto comercial del propietario (control plane, no login operativo).
            $tabla->string('contacto_nombre');
            $tabla->string('contacto_email');
            $tabla->string('contacto_telefono')->nullable();

            // Trial y plan (precio por alumno activo; dinero en minor + moneda).
            $tabla->date('trial_inicia_en')->nullable();
            $tabla->date('trial_termina_en')->nullable();
            $tabla->string('plan')->default('trial');
            $tabla->unsignedBigInteger('precio_por_alumno_minor')->default(0);
            $tabla->char('moneda', 3)->default('MXN');
            $tabla->string('estado_facturacion')->default('trialing')->index();

            // Config de la BD del tenant (data plane). El gestor de conexión la usa.
            $tabla->string('db_driver')->default('sqlite');
            $tabla->string('db_database')->nullable(); // archivo sqlite o nombre de BD
            $tabla->string('version_migraciones')->nullable();

            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudios');
    }
};
