<?php

namespace App\Modules\Desarrollo\Domain\Services;

/**
 * Qué micro-contenidos están Asignados a un colaborador: Publicados y (vinculados a una competencia con brecha activa y
 * aún sin completar, o ya iniciados y sin completar). Un contenido completado nunca está asignado.
 */
final class CalculadorDeAsignacion
{
    public const MOTIVO_BRECHA = 'brecha';

    public const MOTIVO_INICIADO = 'iniciado';

    /**
     * @param  array<int, int>  $publicadosPorBrecha  Ids de los Publicados vinculados a competencias con brecha activa.
     * @param  array<int, int>  $iniciadosPublicados  Ids de los Publicados con progreso En progreso.
     * @param  array<int, int>  $completados  Ids ya completados por el colaborador.
     * @return array<int, string> id => motivo (`iniciado` prevalece sobre `brecha`)
     */
    public function asignados(array $publicadosPorBrecha, array $iniciadosPublicados, array $completados): array
    {
        $asignados = [];

        foreach (array_unique($publicadosPorBrecha) as $id) {
            if (! in_array($id, $completados, true)) {
                $asignados[$id] = self::MOTIVO_BRECHA;
            }
        }
        foreach (array_unique($iniciadosPublicados) as $id) {
            if (! in_array($id, $completados, true)) {
                $asignados[$id] = self::MOTIVO_INICIADO;
            }
        }

        return $asignados;
    }
}
