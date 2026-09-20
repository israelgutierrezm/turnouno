<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina FAMILIAS (Hogares y Tutela/dependientes) del data plane: dejan de existir en
 * el producto (decisión del dueño). Quita `hogar_id` de `personas` y borra las tablas
 * `tutelas` (hija) antes que `hogares` (padre), para respetar las llaves foráneas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('personas', function (Blueprint $tabla): void {
            $tabla->dropIndex(['hogar_id']);
            $tabla->dropColumn('hogar_id');
        });

        Schema::connection('tenant')->dropIfExists('tutelas');
        Schema::connection('tenant')->dropIfExists('hogares');
    }

    public function down(): void
    {
        // Irreversible: la función de Familias se retiró del producto.
    }
};
