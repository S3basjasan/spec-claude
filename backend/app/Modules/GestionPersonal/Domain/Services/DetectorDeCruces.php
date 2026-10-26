<?php

namespace App\Modules\GestionPersonal\Domain\Services;

/**
 * Detecta las novedades que comparten al menos un día con un periodo (fechas inclusivas). Solo cuentan permisos,
 * licencias y vacaciones presentados, en revisión o aprobados: una ausencia informada es un hecho, no una autorización.
 * Es una señal para el Administrador, nunca un bloqueo. PHP puro.
 */
final class DetectorDeCruces
{
    private const TIPOS = ['Permiso', 'Licencia', 'Vacaciones'];

    private const ESTADOS = ['Presentada', 'EnRevision', 'Aprobada'];

    /**
     * @param  list<array{id: int, tipo: string, estado: string, inicio: string, fin: string}>  $otras
     * @return list<array{id: int, tipo: string, estado: string, inicio: string, fin: string}>
     */
    public function cruces(string $inicio, string $fin, array $otras, ?int $excluirId = null): array
    {
        $cruces = [];
        foreach ($otras as $otra) {
            if ($otra['id'] === $excluirId
                || ! in_array($otra['tipo'], self::TIPOS, true)
                || ! in_array($otra['estado'], self::ESTADOS, true)) {
                continue;
            }
            if ($inicio <= $otra['fin'] && $otra['inicio'] <= $fin) {
                $cruces[] = $otra;
            }
        }

        return $cruces;
    }
}
