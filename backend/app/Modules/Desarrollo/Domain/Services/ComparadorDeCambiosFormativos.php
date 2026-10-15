<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\ValueObjects\AccionHistorial;
use App\Modules\Desarrollo\Domain\ValueObjects\CambioHistorial;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoCambioHistorial;

/**
 * Compara el estado anterior y el nuevo de la configuración formativa y devuelve las entradas del historial:
 * una por cada vínculo asociado o quitado y una por el cambio de área. Sin diferencias, lista vacía.
 */
final class ComparadorDeCambiosFormativos
{
    /**
     * @param  array<int, string>  $cursosAntes  id => nombre
     * @param  array<int, string>  $cursosAhora  id => nombre
     * @param  array<int, string>  $microsAntes  id => nombre
     * @param  array<int, string>  $microsAhora  id => nombre
     * @return array<int, CambioHistorial>
     */
    public function comparar(
        int $competenciaId,
        ?int $areaAnteriorId,
        ?string $nombreAreaAnterior,
        ?int $areaNuevaId,
        ?string $nombreAreaNueva,
        array $cursosAntes,
        array $cursosAhora,
        array $microsAntes,
        array $microsAhora,
    ): array {
        $cambios = [];

        if ($areaAnteriorId !== $areaNuevaId) {
            $cambios[] = new CambioHistorial(
                $competenciaId,
                TipoCambioHistorial::AreaFormativa,
                AccionHistorial::Cambiado,
                $areaNuevaId,
                $nombreAreaAnterior,
                $nombreAreaNueva,
            );
        }

        return array_merge(
            $cambios,
            $this->vinculos($competenciaId, TipoCambioHistorial::Curso, $cursosAntes, $cursosAhora),
            $this->vinculos($competenciaId, TipoCambioHistorial::Microcontenido, $microsAntes, $microsAhora),
        );
    }

    /**
     * @param  array<int, string>  $antes
     * @param  array<int, string>  $ahora
     * @return array<int, CambioHistorial>
     */
    private function vinculos(int $competenciaId, TipoCambioHistorial $tipo, array $antes, array $ahora): array
    {
        $cambios = [];

        foreach ($ahora as $id => $nombre) {
            if (! array_key_exists($id, $antes)) {
                $cambios[] = new CambioHistorial($competenciaId, $tipo, AccionHistorial::Asociado, $id, null, $nombre);
            }
        }
        foreach ($antes as $id => $nombre) {
            if (! array_key_exists($id, $ahora)) {
                $cambios[] = new CambioHistorial($competenciaId, $tipo, AccionHistorial::Quitado, $id, $nombre, null);
            }
        }

        return $cambios;
    }
}
