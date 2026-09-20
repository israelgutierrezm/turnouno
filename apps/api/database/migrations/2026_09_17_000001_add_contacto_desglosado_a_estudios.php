<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control plane: desglosa el nombre del contacto del propietario y captura su WhatsApp
 * con código de país, para el alta por pasos (filtrar interesados reales). El
 * `contacto_nombre` pasa a ser el PRIMER nombre; el nombre completo se compone con las
 * partes (ver Estudio::nombreContacto). `contacto_telefono` guarda el número de
 * WhatsApp y `contacto_whatsapp_pais` su lada (default México, 52).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudios', function (Blueprint $tabla): void {
            $tabla->string('contacto_segundo_nombre')->nullable()->after('contacto_nombre');
            $tabla->string('contacto_primer_apellido')->nullable()->after('contacto_segundo_nombre');
            $tabla->string('contacto_segundo_apellido')->nullable()->after('contacto_primer_apellido');
            $tabla->string('contacto_whatsapp_pais', 5)->default('52')->after('contacto_email');
        });
    }

    public function down(): void
    {
        Schema::table('estudios', function (Blueprint $tabla): void {
            $tabla->dropColumn([
                'contacto_segundo_nombre',
                'contacto_primer_apellido',
                'contacto_segundo_apellido',
                'contacto_whatsapp_pais',
            ]);
        });
    }
};
