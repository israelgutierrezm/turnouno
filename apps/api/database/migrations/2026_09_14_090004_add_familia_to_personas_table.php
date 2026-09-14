<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table): void {
            $table->foreignId('hogar_id')->nullable()->after('user_id')->constrained('hogares')->nullOnDelete();
            $table->date('fecha_nacimiento')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hogar_id');
            $table->dropColumn('fecha_nacimiento');
        });
    }
};
