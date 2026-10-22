<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Domain\Exceptions\CompetenciaInvalidaEnMicro;

/** Reglas sobre las competencias de un micro-contenido: las nuevas deben estar activas; las ya vinculadas se conservan. */
final class ValidadorDeCompetenciasMicro
{
    public function __construct(private readonly CompetenciasInterface $competencias) {}

    /**
     * @param  array<int, int>  $solicitadas
     * @param  array<int, int>  $yaVinculadas
     *
     * @throws CompetenciaInvalidaEnMicro
     */
    public function validarNuevas(array $solicitadas, array $yaVinculadas = []): void
    {
        foreach (array_diff($solicitadas, $yaVinculadas) as $id) {
            if (! $this->competencias->estaActiva($id)) {
                throw new CompetenciaInvalidaEnMicro;
            }
        }
    }

    /**
     * Cantidad de competencias activas entre las dadas (las inactivas no cuentan para publicar).
     *
     * @param  array<int, int>  $ids
     */
    public function contarActivas(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return count(array_filter($this->competencias->porIds($ids), fn (array $c) => $c['estado'] === 'Activo'));
    }
}
