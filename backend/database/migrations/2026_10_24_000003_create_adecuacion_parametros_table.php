<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adecuacion_parametros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique(); // una sola fila vigente por tenant
            $table->unsignedTinyInteger('peso_competencias'); // 0-100; los tres pesos suman 100
            $table->unsignedTinyInteger('peso_conocimientos');
            $table->unsignedTinyInteger('peso_experiencia');
            $table->unsignedSmallInteger('umbral_meses'); // >= 1; inicial 12
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adecuacion_parametros');
    }
};
