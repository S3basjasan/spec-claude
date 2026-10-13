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
        // Historial de solo inserción: sin updated_at, sin borrado en cascada y sin claves foráneas
        // (la entidad auditada se identifica por `entidad` y `entidad_id`).
        Schema::create('auditoria_cambios', function (Blueprint $table) {
            $table->id();
            $table->string('entidad', 30); // `puesto` en esta feature
            $table->unsignedBigInteger('entidad_id');
            $table->string('campo', 30);
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            // Id de usuario sin clave foránea: Estructura no accede a las tablas de otro módulo.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['entidad', 'entidad_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditoria_cambios');
    }
};
