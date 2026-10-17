<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\MicroDTO;
use App\Modules\Desarrollo\Application\Services\AccesoDeColaborador;
use App\Modules\Desarrollo\Application\Services\ArmadorDeMicro;
use App\Modules\Desarrollo\Domain\Exceptions\MicroNoEncontrado;
use App\Modules\Desarrollo\Domain\Repositories\MicrocontenidoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\ProgresoMicroRepositoryInterface;

/**
 * Detalle de un micro-contenido. El Administrador lo ve en cualquier estado, con la opción correcta y los datos de
 * auditoría; el colaborador, solo si está Publicado (o Archivado y ya lo completó), sin la opción correcta y con su
 * progreso.
 */
final class ObtenerMicrocontenido
{
    public function __construct(
        private readonly MicrocontenidoRepositoryInterface $repositorio,
        private readonly ProgresoMicroRepositoryInterface $progresos,
        private readonly AccesoDeColaborador $acceso,
        private readonly ArmadorDeMicro $armador,
    ) {}

    public function ejecutar(int $id, bool $esAdmin, int $usuarioId): MicroDTO
    {
        if ($esAdmin) {
            $micro = $this->repositorio->obtener($id) ?? throw new MicroNoEncontrado;

            return $this->armador->armar([$micro], true, true)[0];
        }

        $visible = $this->acceso->visible($id, $usuarioId);
        $progreso = $visible['progreso'];
        $arreglo = $progreso === null
            ? []
            : [$id => $this->armador->progresoComoArreglo($progreso, $this->progresos->ultimoPuntaje($progreso->id))];

        return $this->armador->armar([$visible['micro']], false, false, $arreglo)[0];
    }
}
