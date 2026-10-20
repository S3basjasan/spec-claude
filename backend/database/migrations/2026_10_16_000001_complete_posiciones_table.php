<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
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
        // Idempotente: si la columna ya existe (BDs migradas a mano o corridas parciales en SQLite), se omite.
        Schema::table('posiciones', function (Blueprint $table) {
            if (! Schema::hasColumn('posiciones', 'codigo')) {
                $table->string('codigo', 20)->nullable()->unique();
            }
            if (! Schema::hasColumn('posiciones', 'user_id')) {
                $table->foreignId('user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            }
            if (! Schema::hasColumn('posiciones', 'posicion_jefe_id')) {
                $table->foreignId('posicion_jefe_id')->nullable()->index()->constrained('posiciones')->restrictOnDelete();
            }
            if (! Schema::hasColumn('posiciones', 'modalidad')) {
                $table->string('modalidad', 30)->default('Nombramiento'); // Nombramiento | Contrato | Encargo | Subrogacion
            }
            if (! Schema::hasColumn('posiciones', 'version')) {
                $table->unsignedInteger('version')->default(1);
            }
            // Ids de usuario sin clave foránea, como en puestos y unidades.
            if (! Schema::hasColumn('posiciones', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }
            if (! Schema::hasColumn('posiciones', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable();
            }
        });

        // Backfill: BDs sembradas con la versión antigua de PuestosSeeder tienen N
        // vigentes sin supervisor y la regla nueva exige una sola máxima autoridad.
        // Se conserva la más antigua como raíz y las demás reportan a ella;
        // PosicionesSeeder reordena después la jerarquía real. Sin esto, el índice
        // parcial no se puede crear (UNIQUE constraint failed).
        $raices = DB::table('posiciones')
            ->whereNull('posicion_jefe_id')
            ->where('estado', '<>', 'Suprimida')
            ->orderBy('id')
            ->pluck('id');
        if ($raices->count() > 1) {
            DB::table('posiciones')
                ->whereNull('posicion_jefe_id')
                ->where('estado', '<>', 'Suprimida')
                ->where('id', '<>', $raices->first())
                ->update(['posicion_jefe_id' => $raices->first()]);
        }

        // Como máximo una posición vigente (no Suprimida) sin supervisor: la máxima autoridad. La expresión constante
        // hace que todas las filas del índice parcial choquen entre sí.
        try {
            DB::statement(
                "create unique index posiciones_una_maxima_autoridad on posiciones ((1)) where posicion_jefe_id is null and estado <> 'Suprimida'"
            );
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'already exists')) {
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists posiciones_una_maxima_autoridad');

        Schema::table('posiciones', function (Blueprint $table) {
            // dropConstrainedForeignId elimina FK + columna.
            if (Schema::hasColumn('posiciones', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
            if (Schema::hasColumn('posiciones', 'posicion_jefe_id')) {
                $table->dropConstrainedForeignId('posicion_jefe_id');
            }
            // unique puede no existir si la columna se creó sin él (BD parchada a mano).
            try {
                $table->dropUnique(['codigo']);
            } catch (QueryException $e) {
                if (! str_contains($e->getMessage(), 'no such index')
                    && ! str_contains($e->getMessage(), 'does not exist')) {
                    throw $e;
                }
            }
            $drop = array_values(array_filter(
                ['codigo', 'modalidad', 'version', 'created_by', 'updated_by'],
                fn (string $c): bool => Schema::hasColumn('posiciones', $c)
            ));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
