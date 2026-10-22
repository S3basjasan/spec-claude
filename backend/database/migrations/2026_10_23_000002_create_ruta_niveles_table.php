<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruta_niveles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('ruta_id')->constrained('rutas')->restrictOnDelete(); // sin cascada
            $table->unsignedSmallInteger('orden'); // consecutivo desde 1 (el 1 es el nivel de ingreso)
            // Referencia lógica a un puesto de Estructura Empresarial: sin clave foránea.
            $table->unsignedBigInteger('puesto_id');
            $table->string('nombre', 80);
            $table->decimal('experiencia_minima_anios', 4, 1)->nullable(); // entre 0 y 60 (validación)
            $table->timestamps();

            $table->unique(['ruta_id', 'orden']);
            $table->unique(['ruta_id', 'puesto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruta_niveles');
    }
};
