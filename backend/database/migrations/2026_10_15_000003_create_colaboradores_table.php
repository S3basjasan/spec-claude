<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colaboradores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('codigo', 40);
            $table->string('tipo_identificacion', 12);
            $table->string('numero_identificacion', 50);
            $table->string('identificacion_clave', 50);
            $table->string('nombres', 150);
            $table->string('apellidos', 150);
            $table->string('nombres_busqueda', 150);
            $table->string('apellidos_busqueda', 150);
            $table->date('fecha_nacimiento')->nullable();
            $table->string('nacionalidad', 100)->nullable();
            $table->string('genero', 80)->nullable();
            $table->string('estado_civil', 80)->nullable();
            $table->unsignedInteger('numero_hijos')->nullable();
            $table->string('direccion', 500)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo_contacto', 254)->nullable();
            $table->string('emergencia_nombre', 150)->nullable();
            $table->string('emergencia_relacion', 100)->nullable();
            $table->string('emergencia_telefono', 30)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->char('operacion_id', 36);
            $table->char('operacion_hash', 64);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->unique(['tenant_id', 'codigo']);
            $table->unique(['tenant_id', 'tipo_identificacion', 'identificacion_clave'], 'colaboradores_identificacion_unica');
            $table->unique(['tenant_id', 'operacion_id']);
            $table->index(['tenant_id', 'apellidos_busqueda', 'nombres_busqueda', 'id'], 'colaboradores_orden_listado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colaboradores');
    }
};
