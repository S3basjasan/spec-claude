<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeActuacion;
use App\Modules\GestionPersonal\Application\Services\TransicionadorDeActuacion;
use App\Modules\GestionPersonal\Domain\Entities\Actuacion;
use App\Modules\GestionPersonal\Domain\Exceptions\SolicitudNoEncontrada;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeActuacion;

/** El Administrador toma una solicitud presentada y la pasa a «En revisión». */
final class PasarARevision
{
    public function __construct(
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly TransicionadorDeActuacion $transicionador,
    ) {}

    public function ejecutar(ContextoDeTenant $contexto, int $userId, int $id, int $version, ?string $clave): ResultadoDeActuacion
    {
        $previa = $this->actuaciones->porId($contexto->tenantId, $id) ?? throw new SolicitudNoEncontrada;

        return $this->transicionador->ejecutar($contexto, $userId, $previa, $version, 'revision', $clave, [], fn (Actuacion $actual) => [
            'nuevo' => EstadoDeActuacion::EnRevision,
            'evento' => 'PasoARevision',
            'justificacion' => null,
            'detalle' => [],
            'novedad' => [],
            'movimiento' => [],
        ]);
    }
}
