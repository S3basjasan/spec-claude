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
        // Completa la tabla `posiciones` (creada mínima en 004 y ampliada con la unidad en 005). Las columnas nuevas son
        // anulables o tienen valor por defecto para convivir con las filas que ya existen; la aplicación exige el código
        // y la unidad al crear. Las posiciones nunca se eliminan (sin cascada).
        Schema::table('posiciones', function (Blueprint $table) {
            $table->string('codigo', 20)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('posicion_jefe_id')->nullable()->index()->constrained('posiciones')->restrictOnDelete();
            $table->string('modalidad', 30)->default('Nombramiento'); // Nombramiento | Contrato | Encargo | Subrogacion
            $table->unsignedInteger('version')->default(1);
            // Ids de usuario sin clave foránea, como en puestos y unidades.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });

        // Como máximo una posición vigente (no Suprimida) sin supervisor: la máxima autoridad. La expresión constante
        // hace que todas las filas del índice parcial choquen entre sí.
        DB::statement(
            "create unique index posiciones_una_maxima_autoridad on posiciones ((1)) where posicion_jefe_id is null and estado <> 'Suprimida'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists posiciones_una_maxima_autoridad');

        Schema::table('posiciones', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropConstrainedForeignId('posicion_jefe_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['codigo', 'modalidad', 'version', 'created_by', 'updated_by']);
        });
    }
};
