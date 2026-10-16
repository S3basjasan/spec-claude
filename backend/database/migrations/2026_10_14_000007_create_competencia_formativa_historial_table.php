<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historial de SOLO inserción: sin updated_at y sin endpoints de edición ni borrado.
        Schema::create('competencia_formativa_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competencia_id')->nullable()->index(); // nulo en renombrados de área
            $table->string('tipo_cambio', 15); // AreaFormativa | Curso | Microcontenido
            $table->string('accion', 12); // Asociado | Quitado | Cambiado | Renombrado
            $table->unsignedBigInteger('recurso_id')->nullable();
            $table->string('valor_anterior', 255)->nullable();
            $table->string('valor_nuevo', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competencia_formativa_historial');
    }
};
