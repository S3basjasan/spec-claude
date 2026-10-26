<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeDetalleDeActuacion;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeSolicitante;
use App\Modules\GestionPersonal\Domain\Exceptions\CuentaSinFicha;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;

/**
 * Solicitudes de la propia ficha: todas las novedades solicitadas, las ausencias ya revisadas o justificadas y los
 * movimientos aprobados, programados o aplicados (solo resultado y fecha efectiva). Sin directorio ni otras fichas.
 */
final class ListarMisSolicitudes
{
    public function __construct(
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly ResolvedorDeSolicitante $solicitantes,
        private readonly ArmadorDeDetalleDeActuacion $armador,
    ) {}

    /**
     * @return list<array<string, mixed>>
     *
     * @throws CuentaSinFicha
     */
    public function ejecutar(ContextoDeTenant $contexto, int $userId, ?string $tipo, ?string $estado): array
    {
        $solicitante = $this->solicitantes->colaborador($contexto, $userId);

        $salida = [];
        foreach ($this->actuaciones->delColaborador($contexto->tenantId, $solicitante->colaboradorId) as $actuacion) {
            if (! $this->armador->esVisibleParaColaborador($actuacion)) {
                continue;
            }
            $vista = $this->armador->paraColaborador($contexto, $actuacion);
            if (($tipo !== null && $vista['tipo'] !== $tipo) || ($estado !== null && $vista['estado'] !== $estado)) {
                continue;
            }
            $salida[] = $vista;
        }

        return $salida;
    }
}
