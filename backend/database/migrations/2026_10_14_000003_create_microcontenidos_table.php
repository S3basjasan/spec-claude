<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla MÍNIMA de micro-contenidos: Micro-Learning la amplía con migraciones propias; no recrearla.
        Schema::create('microcontenidos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('tipo', 15); // Video | Lectura | Infografia | Audio
            $table->unsignedInteger('duracion_minutos');
            $table->string('formador', 80)->nullable();
            $table->string('estado', 12)->default('Disponible')->index(); // Disponible | Archivado
            $table->string('url_destino', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microcontenidos');
    }
};
