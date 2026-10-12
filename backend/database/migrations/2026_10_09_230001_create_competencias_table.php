<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('competencias', function (Blueprint $table) {
            $table->id();
            // Anulable solo mientras se crea la fila: se asigna (COMP-NNNN) en la misma transacción.
            $table->string('codigo')->nullable()->unique();
            $table->string('nombre', 50)->collation('nocase');
            // Nombre en minúsculas (Unicode) y recortado: unicidad sin distinguir mayúsculas ni espacios.
            $table->string('nombre_clave', 50)->unique();
            $table->string('descripcion', 500);
            $table->string('tipo', 20); // Cardinal | Especifica
            $table->string('estado', 10)->default('Activo')->index(); // Activo | Inactivo
            $table->unsignedInteger('version')->default(1);
            // Ids de usuario sin clave foránea: Estructura no accede a las tablas de otro módulo.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competencias');
    }
};
