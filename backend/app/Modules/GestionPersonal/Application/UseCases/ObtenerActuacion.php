<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeDetalleDeActuacion;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeSolicitante;
use App\Modules\GestionPersonal\Domain\Exceptions\SolicitudNoEncontrada;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;

/**
 * Detalle de una actuación. Un registro inexistente, de otro tenant o ajeno al Colaborador (o aún no comunicable)
 * responde siempre `SolicitudNoEncontrada`: no se distingue de uno que no existe.
 */
final class ObtenerActuacion
{
    public function __construct(
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly ResolvedorDeSolicitante $solicitantes,
        private readonly ArmadorDeDetalleDeActuacion $armador,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws SolicitudNoEncontrada
     */
    public function comoAdministrador(ContextoDeTenant $contexto, int $id): array
    {
        $actuacion = $this->actuaciones->porId($contexto->tenantId, $id) ?? throw new SolicitudNoEncontrada;

        return $this->armador->paraAdministrador($contexto, $actuacion);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SolicitudNoEncontrada
     */
    public function comoColaborador(ContextoDeTenant $contexto, int $userId, int $id): array
    {
        $solicitante = $this->solicitantes->colaborador($contexto, $userId);
        $actuacion = $this->actuaciones->porId($contexto->tenantId, $id);
        if ($actuacion === null || $actuacion->colaboradorId !== $solicitante->colaboradorId || ! $this->armador->esVisibleParaColaborador($actuacion)) {
            throw new SolicitudNoEncontrada;
        }

        return $this->armador->paraColaborador($contexto, $actuacion);
    }
}
