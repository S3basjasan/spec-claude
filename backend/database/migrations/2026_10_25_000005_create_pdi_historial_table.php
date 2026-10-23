<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdi_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('pdi_id')->constrained('pdis')->restrictOnDelete();
            $table->string('tipo', 12); // Creacion | Edicion | Estado | Acuerdo | Aceptacion | Devolucion | Responsable | Vinculo | Avance | Hito | Cierre
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('valor_anterior', 255)->nullable();
            $table->string('valor_nuevo', 255)->nullable();
            $table->string('motivo', 1000)->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('created_at')->nullable(); // solo inserciones: no hay updated_at

            $table->index(['pdi_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdi_historial');
    }
};
