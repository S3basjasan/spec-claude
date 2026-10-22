<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo maestro de categorías de reconocimiento (feature 016). Nunca se elimina: el estado es un campo de negocio.
        Schema::create('categorias_reconocimiento', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique(); // CAT-###, inmutable
            $table->string('nombre', 100);
            $table->string('nombre_normalizado', 100)->unique(); // minúsculas, sin tildes y espacios colapsados
            $table->text('descripcion');
            $table->foreignId('competencia_id')->constrained('competencias')->restrictOnDelete();
            $table->unsignedSmallInteger('puntuacion_base'); // 1 a 1000, validado en la aplicación
            $table->string('icono', 50);
            $table->string('estado', 10)->default('Activo')->index(); // Activo | Inactivo
            // Ids de usuario sin clave foránea: este módulo no accede a las tablas de otro.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            // Precisión de microsegundos: updated_at es el token de concurrencia optimista.
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_reconocimiento');
    }
};
