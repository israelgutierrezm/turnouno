<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Derecho (entitlement): lo que un acuerdo otorga. El saldo NO se almacena aquí;
// se deriva del ledger de créditos (movimientos_credito).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('derechos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acuerdo_id')->constrained('acuerdos')->cascadeOnDelete();
            $table->string('ambito')->default('general');
            $table->boolean('ilimitado')->default(false);
            $table->date('valido_desde')->nullable();
            $table->date('valido_hasta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('derechos');
    }
};
