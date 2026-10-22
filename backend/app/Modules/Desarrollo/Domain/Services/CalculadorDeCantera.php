<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Entities\InscripcionARuta;

/** Cantera de talento: cuántos colaboradores con inscripción Activa hay en cada nivel actual de una ruta. */
final class CalculadorDeCantera
{
    /**
     * @param  list<InscripcionARuta>  $inscripciones  las de la ruta; las que no estén Activas se ignoran
     * @param  list<int>  $nivelIds
     * @return array{por_nivel: array<int, int>, sin_nivel: int, total: int}
     */
    public function contar(array $inscripciones, array $nivelIds): array
    {
        $porNivel = array_fill_keys($nivelIds, 0);
        $sinNivel = 0;
        $total = 0;

        foreach ($inscripciones as $i) {
            if (! $i->estaActiva()) {
                continue;
            }
            $total++;
            if ($i->nivelActualId === null || ! array_key_exists($i->nivelActualId, $porNivel)) {
                $sinNivel++;

                continue;
            }
            $porNivel[$i->nivelActualId]++;
        }

        return ['por_nivel' => $porNivel, 'sin_nivel' => $sinNivel, 'total' => $total];
    }
}
