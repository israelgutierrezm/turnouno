<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data plane (BD del tenant): multi-rol por usuario. Una misma persona puede ser,
 * dentro del mismo estudio, miembro y profesor, o miembro y administrador. Los roles
 * se guardan como arreglo JSON en `roles`; `rol` se conserva como rol PRINCIPAL
 * (el mas privilegiado) para compatibilidad con consultas/vistas existentes.
 *
 * Backfill: cada usuario existente arranca con `roles = [rol]`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('users', function (Blueprint $tabla): void {
            $tabla->json('roles')->nullable()->after('rol');
        });

        // Siembra `roles` a partir del rol unico existente. La tabla de usuarios por
        // tenant es pequena (staff del estudio), asi que un recorrido simple basta.
        $usuarios = DB::connection('tenant')->table('users')->select('id', 'rol')->get();
        foreach ($usuarios as $usuario) {
            $rol = is_string($usuario->rol) && $usuario->rol !== '' ? $usuario->rol : 'miembro';
            DB::connection('tenant')->table('users')
                ->where('id', $usuario->id)
                ->update(['roles' => json_encode([$rol])]);
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('users', function (Blueprint $tabla): void {
            $tabla->dropColumn('roles');
        });
    }
};
