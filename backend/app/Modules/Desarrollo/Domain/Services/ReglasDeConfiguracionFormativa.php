<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Exceptions\AreaNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\CompetenciaInactiva;
use App\Modules\Desarrollo\Domain\Exceptions\RecursoNoDisponible;
use App\Modules\Desarrollo\Domain\ValueObjects\DatosConfiguracion;

/**
 * Reglas puras de la configuración formativa de una competencia (sin Laravel ni Eloquent):
 * sin ids repetidos, área existente, recursos nuevos disponibles y sin recursos nuevos en competencias inactivas.
 */
final class ReglasDeConfiguracionFormativa
{
    /**
     * @param  array<int, int>  $cursosVinculados  Ids ya vinculados (incluso archivados).
     * @param  array<int, int>  $microsVinculados
     * @param  array<int, int>  $cursosDisponibles  Ids de cursos que existen y están disponibles.
     * @param  array<int, int>  $microsDisponibles
     * @param  array<int, int>  $areasExistentes
     *
     * @throws RecursoNoDisponible
     * @throws AreaNoEncontrada
     * @throws CompetenciaInactiva
     */
    public function validar(
        bool $competenciaActiva,
        DatosConfiguracion $solicitado,
        array $cursosVinculados,
        array $microsVinculados,
        array $cursosDisponibles,
        array $microsDisponibles,
        array $areasExistentes,
    ): void {
        $repetidos = $this->repetidos('curso_ids', $solicitado->cursoIds, 'El curso está repetido.')
            + $this->repetidos('microcontenido_ids', $solicitado->microcontenidoIds, 'El micro-contenido está repetido.');
        if ($repetidos !== []) {
            throw new RecursoNoDisponible($repetidos);
        }

        if ($solicitado->areaFormativaId !== null && ! in_array($solicitado->areaFormativaId, $areasExistentes, true)) {
            throw new AreaNoEncontrada;
        }

        $cursosNuevos = array_diff($solicitado->cursoIds, $cursosVinculados);
        $microsNuevos = array_diff($solicitado->microcontenidoIds, $microsVinculados);

        if (! $competenciaActiva && ($cursosNuevos !== [] || $microsNuevos !== [])) {
            throw new CompetenciaInactiva;
        }

        $errores = $this->noDisponibles('curso_ids', $solicitado->cursoIds, $cursosNuevos, $cursosDisponibles, 'El curso no existe o no está disponible.')
            + $this->noDisponibles('microcontenido_ids', $solicitado->microcontenidoIds, $microsNuevos, $microsDisponibles, 'El micro-contenido no existe o no está disponible.');
        if ($errores !== []) {
            throw new RecursoNoDisponible($errores);
        }
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<string, string>
     */
    private function repetidos(string $campo, array $ids, string $mensaje): array
    {
        $vistos = [];
        $errores = [];
        foreach ($ids as $indice => $id) {
            if (isset($vistos[$id])) {
                $errores["$campo.$indice"] = $mensaje;
            }
            $vistos[$id] = true;
        }

        return $errores;
    }

    /**
     * @param  array<int, int>  $solicitados
     * @param  array<int, int>  $nuevos
     * @param  array<int, int>  $disponibles
     * @return array<string, string>
     */
    private function noDisponibles(string $campo, array $solicitados, array $nuevos, array $disponibles, string $mensaje): array
    {
        $errores = [];
        foreach ($solicitados as $indice => $id) {
            if (in_array($id, $nuevos, true) && ! in_array($id, $disponibles, true)) {
                $errores["$campo.$indice"] = $mensaje;
            }
        }

        return $errores;
    }
}
