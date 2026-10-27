<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeSalida;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeSolicitante;
use App\Modules\GestionPersonal\Application\Services\TransicionadorDeExpediente;
use App\Modules\GestionPersonal\Application\Services\ValidadorDeRespaldos;
use App\Modules\GestionPersonal\Domain\Entities\Expediente;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EstadoNoPermitido;
use App\Modules\GestionPersonal\Domain\Exceptions\EvidenciaRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\ExpedienteNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeSalida;

/**
 * Registra el cumplimiento de una tarea de la plantilla. Una tarea con evidencia obligatoria exige al menos un respaldo
 * disponible y accesible. La tarea de entrevista no se completa a mano: la resuelve el proceso de la entrevista.
 */
final class CompletarTarea
{
    public function __construct(
        private readonly ExpedienteRepositoryInterface $expedientes,
        private readonly ResolvedorDeSolicitante $solicitantes,
        private readonly ValidadorDeRespaldos $respaldos,
        private readonly TransicionadorDeExpediente $transicionador,
    ) {}

    /**
     * @param  list<mixed>  $documentoIds
     *
     * @throws ExpedienteNoEncontrado
     * @throws DatosInvalidos
     * @throws EvidenciaRequerida
     * @throws EstadoNoPermitido
     */
    public function ejecutar(ContextoDeTenant $contexto, int $userId, int $id, int $tareaId, int $version, array $documentoIds, ?string $clave): ResultadoDeSalida
    {
        $previa = $this->expedientes->porId($contexto->tenantId, $id) ?? throw new ExpedienteNoEncontrado;
        $tarea = $previa->tarea($tareaId) ?? throw new ExpedienteNoEncontrado;

        $solicitante = $this->solicitantes->administrador($contexto, $userId, $previa->colaboradorId);
        $respaldos = $this->respaldos->validar($contexto->tenantId, $solicitante, $documentoIds);
        $ordenados = $respaldos;
        sort($ordenados);

        return $this->transicionador->ejecutar($contexto, $userId, $previa, $version, 'completar_tarea', $clave, [$tareaId, $ordenados], function (Expediente $actual, string $clave, string $huella) use ($userId, $tareaId, $respaldos) {
            if ($actual->estado === EstadoDeSalida::Cancelada || $actual->estaCerrado()) {
                throw new EstadoNoPermitido('El expediente ya no admite cambios en sus tareas.');
            }
            $tarea = $actual->tarea($tareaId) ?? throw new ExpedienteNoEncontrado;
            if ($tarea['tipo'] === 'Entrevista') {
                throw new EstadoNoPermitido('La tarea de entrevista se resuelve con la entrevista, no manualmente.');
            }
            if ($tarea['estado'] === 'Completada') {
                throw new EstadoNoPermitido('La tarea ya está completada.');
            }
            if ($tarea['exige_evidencia'] && $respaldos === []) {
                throw new EvidenciaRequerida;
            }

            return $this->expedientes->completarTarea($actual, $tareaId, $userId, $respaldos, $clave, $huella);
        });
    }
}
