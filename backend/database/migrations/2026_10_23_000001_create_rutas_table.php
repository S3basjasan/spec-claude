<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rutas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index(); // sin FK
            $table->unsignedInteger('numero');
            $table->string('codigo', 10); // CAR-0001
            $table->string('nombre', 80);
            $table->string('nombre_clave', 80); // nombre normalizado en minúsculas: unicidad sin distinguir mayúsculas ni espacios
            $table->string('tipo', 20); // Especialista | Gerencial | Interareas | JovenesProfesionales
            $table->string('descripcion', 500)->nullable();
            // Referencia lógica a una unidad de Estructura Empresarial: sin clave foránea. Nulo en las rutas Interareas.
            $table->unsignedBigInteger('departamento_id')->nullable()->index();
            $table->string('estado', 10)->default('Borrador')->index(); // Borrador | Vigente | Archivada
            $table->unsignedInteger('version')->default(1); // control optimista (409)
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'numero']);
            $table->unique(['tenant_id', 'codigo']);
            $table->unique(['tenant_id', 'nombre_clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rutas');
    }
};
