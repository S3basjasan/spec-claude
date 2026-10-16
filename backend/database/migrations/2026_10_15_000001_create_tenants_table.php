<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('zona_horaria', 64)->default('America/Guayaquil');
            $table->timestamps();
        });

        // Tenant por defecto de las cuentas existentes (features 000 a 004).
        DB::table('tenants')->insert([
            'id' => 1,
            'nombre' => 'Altavista S.A.',
            'zona_horaria' => 'America/Guayaquil',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
