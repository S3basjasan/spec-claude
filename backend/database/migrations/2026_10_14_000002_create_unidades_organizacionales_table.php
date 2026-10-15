<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('unidades_organizacionales', function (Blueprint $table) {
            $table->id();
            // Se asigna `UO-` + id (al menos 3 dígitos) en la misma transacción que la inserción.
            $table->string('codigo', 20)->nullable()->unique();
            $table->string('nombre', 150); // no único
            // Las unidades y los tipos nunca se eliminan (sin cascada).
            $table->foreignId('tipo_unidad_id')->index()->constrained('tipos_unidad')->restrictOnDelete();
            $table->foreignId('unidad_padre_id')->nullable()->index()->constrained('unidades_organizacionales')->restrictOnDelete();
            $table->string('estado', 10)->default('Activa')->index(); // Activa | Inactiva
            $table->date('fecha_creacion');
            $table->date('fecha_supresion')->nullable();
            $table->unsignedInteger('version')->default(1);
            // Ids de usuario sin clave foránea: Estructura no accede a las tablas de otro módulo.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // Como máximo una unidad raíz activa, incluso con peticiones simultáneas. El generador de esquemas no
        // soporta índices parciales; todas las filas que cumplen la condición comparten el valor de `estado`.
        DB::statement("create unique index unidades_una_raiz_activa on unidades_organizacionales (estado) where unidad_padre_id is null and estado = 'Activa'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists unidades_una_raiz_activa');
        Schema::dropIfExists('unidades_organizacionales');
    }
};
