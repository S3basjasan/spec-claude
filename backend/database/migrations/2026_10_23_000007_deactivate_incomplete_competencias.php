<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizarNombresClave();

        $completas = $this->idsDeCompetenciasCompletas();

        $incompletas = DB::table('competencias')
            ->where('estado', 'Activo')
            ->whereNotIn('id', $completas)
            ->pluck('id');

        foreach ($incompletas as $id) {
            DB::table('competencias')->where('id', $id)->update([
                'estado' => 'Inactivo',
                'version' => DB::raw('version + 1'),
            ]);

            DB::table('competencia_estado_historial')->insert([
                'competencia_id' => $id,
                'estado_anterior' => 'Activo',
                'estado_nuevo' => 'Inactivo',
                'user_id' => null,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Los cambios de estado quedan en el historial inmutable: no se revierten.
    }

    /**
     * El nombre de la competencia no distingue mayúsculas, tildes ni espacios laterales: se recalcula la clave
     * de las competencias existentes. Si dos nombres chocaran al quitar tildes, se conserva la clave anterior.
     */
    private function normalizarNombresClave(): void
    {
        foreach (DB::table('competencias')->select('id', 'nombre', 'nombre_clave')->get() as $competencia) {
            $clave = self::claveDeNombre($competencia->nombre);

            if ($clave === $competencia->nombre_clave) {
                continue;
            }

            try {
                DB::table('competencias')->where('id', $competencia->id)->update(['nombre_clave' => $clave]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }
    }

    private static function claveDeNombre(string $nombre): string
    {
        $descompuesto = Normalizer::normalize(trim($nombre), Normalizer::FORM_D);

        return mb_strtolower(preg_replace('/\p{Mn}/u', '', $descompuesto));
    }

    /**
     * Competencias con 4 grados, 16 comportamientos con texto (4 por grado) y 4 preguntas con texto.
     *
     * @return array<int, int>
     */
    private function idsDeCompetenciasCompletas(): array
    {
        $completas = [];

        foreach (DB::table('competencias')->pluck('id') as $id) {
            $grados = DB::table('competencia_grados')->where('competencia_id', $id)->pluck('id');

            if ($grados->count() !== 4) {
                continue;
            }

            $comportamientosCompletos = $grados->every(fn ($gradoId) => DB::table('competencia_comportamientos')
                ->where('competencia_grado_id', $gradoId)
                ->whereRaw("trim(descripcion) <> ''")
                ->count() === 4);

            $preguntas = DB::table('competencia_preguntas')
                ->where('competencia_id', $id)
                ->whereRaw("trim(pregunta) <> ''")
                ->count();

            if ($comportamientosCompletos && $preguntas === 4) {
                $completas[] = $id;
            }
        }

        return $completas;
    }
};
