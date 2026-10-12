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
        // Cabecera del registro de comportamientos de una competencia: auditoría, versión y unicidad.
        Schema::create('competencia_comportamiento_registros', function (Blueprint $table) {
            $table->id();
            // Una competencia, un solo registro; las competencias nunca se eliminan (sin cascada).
            $table->foreignId('competencia_id')->unique()->constrained('competencias')->restrictOnDelete();
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
        Schema::dropIfExists('competencia_comportamiento_registros');
    }
};
