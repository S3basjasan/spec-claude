<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adecuacion_parametros_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('campo', 30); // peso_competencias | peso_conocimientos | peso_experiencia | umbral_meses
            $table->string('valor_anterior', 20);
            $table->string('valor_nuevo', 20);
            $table->unsignedBigInteger('user_id');
            $table->timestamp('created_at')->nullable(); // solo se inserta: no se actualiza ni se borra

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adecuacion_parametros_historial');
    }
};
