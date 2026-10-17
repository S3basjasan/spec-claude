<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historial de SOLO inserción: sin updated_at y sin endpoints de edición ni borrado.
        Schema::create('microcontenido_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('microcontenido_id')->constrained('microcontenidos')->restrictOnDelete();
            $table->string('estado_anterior', 10)->nullable(); // nulo en la creación
            $table->string('estado_nuevo', 10);
            $table->unsignedBigInteger('user_id')->nullable(); // sin FK
            $table->timestamp('created_at')->nullable();

            $table->index(['microcontenido_id', 'created_at'], 'micro_historial_contenido_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microcontenido_historial');
    }
};
