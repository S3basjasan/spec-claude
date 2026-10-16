<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\AvisosDTO;
use App\Modules\Desarrollo\Application\DTO\DnfDTO;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDnf;
use App\Modules\Desarrollo\Domain\Exceptions\DnfNoEncontrada;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;

/**
 * Detalle de una DNF del tenant, en solo lectura. En una masiva el resumen no incluye brechas: se piden las de un
 * colaborador con `$colaboradorId` (404 si no pertenece a la DNF). Una individual siempre las incluye.
 */
final class ObtenerDnf
{
    public function __construct(
        private readonly DnfRepositoryInterface $repositorio,
        private readonly ArmadorDeDnf $armador,
    ) {}

    /**
     * @return array{dnf: DnfDTO, avisos: AvisosDTO}
     *
     * @throws DnfNoEncontrada
     */
    public function ejecutar(int $tenantId, int $id, ?int $colaboradorId = null): array
    {
        $dnf = $this->repositorio->obtener($tenantId, $id, $colaboradorId);
        if ($dnf === null) {
            throw new DnfNoEncontrada;
        }

        // Los colaboradores excluidos al generar no se guardan: el detalle solo repite lo que sí forma parte del diagnóstico.
        return [
            'dnf' => $this->armador->armar($dnf, $colaboradorId !== null),
            'avisos' => new AvisosDTO($dnf->competenciasOmitidas, $dnf->sinEvaluacion(), []),
        ];
    }
}
