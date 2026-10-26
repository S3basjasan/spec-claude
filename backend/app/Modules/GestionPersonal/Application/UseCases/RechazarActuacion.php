<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeActuacion;
use App\Modules\GestionPersonal\Application\Services\TransicionadorDeActuacion;
use App\Modules\GestionPersonal\Domain\Entities\Actuacion;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EstadoNoResoluble;
use App\Modules\GestionPersonal\Domain\Exceptions\SolicitudNoEncontrada;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeActuacion;

/** Rechaza una novedad o un movimiento con razón: la resolución se conserva con decisor, momento y justificación. */
final class RechazarActuacion
{
    public const JUSTIFICACION_MAXIMA = 1000;

    public function __construct(
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly TransicionadorDeActuacion $transicionador,
    ) {}

    /**
     * @throws DatosInvalidos
     */
    public function ejecutar(ContextoDeTenant $contexto, int $userId, int $id, int $version, mixed $justificacion, ?string $clave): ResultadoDeActuacion
    {
        $razon = is_string($justificacion) ? trim($justificacion) : '';
        if ($razon === '' || mb_strlen($razon) > self::JUSTIFICACION_MAXIMA) {
            throw new DatosInvalidos(['justificacion' => ['La razón del rechazo es obligatoria y admite hasta '.self::JUSTIFICACION_MAXIMA.' caracteres.']]);
        }

        $previa = $this->actuaciones->porId($contexto->tenantId, $id) ?? throw new SolicitudNoEncontrada;

        return $this->transicionador->ejecutar($contexto, $userId, $previa, $version, 'rechazo', $clave, [$razon], function (Actuacion $actual) use ($razon) {
            if (! $actual->estado->esResoluble()) {
                throw new EstadoNoResoluble;
            }

            return [
                'nuevo' => EstadoDeActuacion::Rechazada,
                'evento' => 'Rechazada',
                'justificacion' => $razon,
                'detalle' => [],
                'novedad' => [],
                'movimiento' => [],
            ];
        });
    }
}
