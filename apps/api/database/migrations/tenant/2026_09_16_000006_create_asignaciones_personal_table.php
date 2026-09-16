<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): RBAC con SCOPE por sucursal (R19), portado de
 * ControlDeAcceso (ADR-0008). El `rol` tenant-wide del usuario aplica en todo el
 * estudio; ADEMAS se le puede asignar un rol EN una sucursal concreta. El acceso a
 * una operacion acotada a sucursal es: rol tenant-wide O rol asignado en esa
 * sucursal (aditivo). Sin `tenant_id`: aislamiento por base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('asignaciones_personal', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->ulid('ulid')->unique();
            $tabla->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $tabla->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $tabla->string('rol'); // rol tenant-local que aplica EN esta sucursal
            $tabla->timestamps();

            $tabla->unique(['usuario_id', 'sucursal_id']);
            $tabla->index('sucursal_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('asignaciones_personal');
    }
};
