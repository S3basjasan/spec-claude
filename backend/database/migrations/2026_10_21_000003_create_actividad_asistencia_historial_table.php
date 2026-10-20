<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividad_asistencia_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('actividades')->restrictOnDelete();
            $table->unsignedBigInteger('colaborador_id');
            $table->string('asistencia_anterior', 12)->nullable(); // nulo = Pendiente
            $table->string('asistencia_nueva', 12)->nullable();
            $table->unsignedTinyInteger('calificacion_anterior')->nullable();
            $table->unsignedTinyInteger('calificacion_nueva')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('created_at')->nullable(); // solo se inserta: no se actualiza ni se borra

            $table->index(['actividad_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad_asistencia_historial');
    }
};
