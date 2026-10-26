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

/** El Administrador revisa o justifica una ausencia informada; nunca la convierte en un permiso aprobado. */
final class RevisarAusencia
{
    public const OBSERVACION_MAXIMA = 500;

    public function __construct(
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly TransicionadorDeActuacion $transicionador,
    ) {}

    /**
     * @throws DatosInvalidos
     */
    public function ejecutar(ContextoDeTenant $contexto, int $userId, int $id, int $version, mixed $resultado, mixed $observacion, ?string $clave): ResultadoDeActuacion
    {
        $estado = is_string($resultado) ? EstadoDeActuacion::tryFrom($resultado) : null;
        if ($estado !== EstadoDeActuacion::Revisada && $estado !== EstadoDeActuacion::Justificada) {
            throw new DatosInvalidos(['resultado' => ['El resultado debe ser Revisada o Justificada.']]);
        }
        $texto = is_string($observacion) ? trim($observacion) : '';
        if ($texto === '' || mb_strlen($texto) > self::OBSERVACION_MAXIMA) {
            throw new DatosInvalidos(['observacion' => ['La observación es obligatoria y admite hasta '.self::OBSERVACION_MAXIMA.' caracteres.']]);
        }

        $previa = $this->actuaciones->porId($contexto->tenantId, $id) ?? throw new SolicitudNoEncontrada;
        if (! $previa->tipo->esAusencia()) {
            throw new EstadoNoResoluble('Solo las ausencias informadas se revisan o justifican.');
        }

        return $this->transicionador->ejecutar($contexto, $userId, $previa, $version, 'revision-de-ausencia', $clave, [$estado->value, $texto], fn (Actuacion $actual) => [
            'nuevo' => $estado,
            'evento' => $estado->value,
            'justificacion' => $texto,
            'detalle' => [],
            'novedad' => ['revision_observacion' => $texto],
            'movimiento' => [],
        ]);
    }
}
