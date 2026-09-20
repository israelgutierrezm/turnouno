<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina las funciones de CRM (embudo de prospectos) y Referidos: dejan de existir en
 * el producto. Se borran sus tablas en la BD de cada estudio (los datos de leads se
 * pierden de forma permanente, por decisión del dueño). Se borran las tablas hijas
 * antes que las padre para respetar las llaves foráneas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conexion = Schema::connection('tenant');

        $conexion->dropIfExists('prospecto_actividades');
        $conexion->dropIfExists('prospectos');
        $conexion->dropIfExists('referidos');
        $conexion->dropIfExists('codigos_referido');
        $conexion->dropIfExists('programa_referidos');
    }

    public function down(): void
    {
        // Irreversible: las funciones de CRM y Referidos se retiraron del producto.
    }
};
