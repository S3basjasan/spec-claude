<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parámetros del módulo Compensaciones (01b.1). Solo inserciones: cada cambio es una fila nueva, que sirve a la
     * vez de historial y de vigencia. `created_by` no lleva llave foránea (relación lógica con Shared).
     */
    public function up(): void
    {
        Schema::create('parametros_modulo', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40);
            $table->integer('valor');
            $table->date('vigente_desde');
            $table->unsignedBigInteger('created_by');
            $table->timestamp('created_at')->nullable();

            $table->index(['clave', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_modulo');
    }
};
