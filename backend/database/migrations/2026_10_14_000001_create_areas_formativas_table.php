<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Áreas formativas del módulo Desarrollo. Se crean y renombran, pero nunca se eliminan.
        Schema::create('areas_formativas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60);
            // Nombre en minúsculas, recortado y con espacios internos colapsados: unicidad sin distinguir mayúsculas.
            $table->string('nombre_clave', 60)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas_formativas');
    }
};
