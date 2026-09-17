<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control plane: perfil de negocio (industria) del estudio (R35). Un SOLO core
 * configurable: el perfil solo ajusta defaults/terminologia/feature-flags, sin forks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudios', function (Blueprint $tabla): void {
            $tabla->string('perfil_negocio')->default('general')->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('estudios', function (Blueprint $tabla): void {
            $tabla->dropColumn('perfil_negocio');
        });
    }
};
