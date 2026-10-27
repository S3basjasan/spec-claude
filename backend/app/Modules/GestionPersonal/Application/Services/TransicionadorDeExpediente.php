<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeSalida;
use App\Modules\GestionPersonal\Domain\Entities\Expediente;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\ExpedienteModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\ExpedienteNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;

/**
 * Operación sobre un expediente existente: idempotente por clave, serializada por el bloqueo de la ficha y con control
 * optimista de versión. La escritura concreta la hace el caso de uso con el expediente recién leído bajo el bloqueo.
 */
final class TransicionadorDeExpediente
{
    public function __construct(
        private readonly ExpedienteRepositoryInterface $expedientes,
        private readonly VinculoLaboralRepositoryInterface $vinculos,
        private readonly ControlDeOffboarding $control,
    ) {}

    /**
     * @param  int|null  $version  Versión esperada del expediente; null cuando el caso de uso valida otra (la de la entrevista).
     * @param  list<mixed>  $parteDeHuella
     * @param  callable(Expediente, string, string): Expediente  $operar  Recibe el expediente actual, la clave y la huella.
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, Expediente $previa, ?int $version, string $accion, ?string $claveDeOperacion, array $parteDeHuella, callable $operar): ResultadoDeSalida
    {
        $clave = $this->control->normalizar($claveDeOperacion);
        $huella = $this->control->huella($accion, $previa->id, $version, $parteDeHuella);

        $repetida = $this->control->repeticion($contexto->tenantId, $clave, $huella, $actorId, $previa->id);
        if ($repetida !== null) {
            return new ResultadoDeSalida($repetida, false);
        }

        try {
            $resultado = $this->vinculos->ejecutarBajoBloqueo($contexto->tenantId, $previa->colaboradorId, function () use ($contexto, $actorId, $previa, $version, $clave, $huella, $operar) {
                $repetida = $this->control->repeticion($contexto->tenantId, $clave, $huella, $actorId, $previa->id);
                if ($repetida !== null) {
                    return new ResultadoDeSalida($repetida, false);
                }

                $actual = $this->expedientes->porId($contexto->tenantId, $previa->id) ?? throw new ExpedienteNoEncontrado;
                if ($version !== null && $actual->version !== $version) {
                    throw new ExpedienteModificado;
                }

                return new ResultadoDeSalida($operar($actual, $clave, $huella), true);
            });
        } catch (ConflictoDeUnicidad $conflicto) {
            $repetida = $this->control->repeticion($contexto->tenantId, $clave, $huella, $actorId, $previa->id);
            if ($repetida !== null) {
                return new ResultadoDeSalida($repetida, false);
            }

            throw $conflicto;
        }

        return $resultado;
    }
}
