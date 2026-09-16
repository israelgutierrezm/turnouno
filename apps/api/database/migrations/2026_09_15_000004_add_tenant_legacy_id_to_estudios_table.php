<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control plane: traza el tenant legacy (esquema compartido) del que se migro un
 * estudio, para que la migracion expand-migrate-verify-cutover sea idempotente
 * (no duplica estudios) y auditable. Nulo para estudios creados por registro nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudios', function (Blueprint $tabla): void {
            $tabla->foreignId('tenant_legacy_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('estudios', function (Blueprint $tabla): void {
            $tabla->dropColumn('tenant_legacy_id');
        });
    }
};
