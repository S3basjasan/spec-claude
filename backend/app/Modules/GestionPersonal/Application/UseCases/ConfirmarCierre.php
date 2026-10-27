<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeSalida;
use App\Modules\GestionPersonal\Application\Services\EvaluadorDeCierre;
use App\Modules\GestionPersonal\Application\Services\TransicionadorDeExpediente;
use App\Modules\GestionPersonal\Domain\Entities\Expediente;
use App\Modules\GestionPersonal\Domain\Exceptions\CierreNoProcede;
use App\Modules\GestionPersonal\Domain\Exceptions\EntrevistaSinResolver;
use App\Modules\GestionPersonal\Domain\Exceptions\EstadoNoPermitido;
use App\Modules\GestionPersonal\Domain\Exceptions\ExpedienteNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
use Illuminate\Support\Carbon;

/**
 * Confirma el cierre administrativo: solo con la salida efectiva, las tareas requeridas completas con su evidencia
 * disponible y la entrevista resuelta. El cierre no altera el vínculo ni la salida; es otro hecho.
 */
final class ConfirmarCierre
{
    public function __construct(
        private readonly ExpedienteRepositoryInterface $expedientes,
        private readonly EvaluadorDeCierre $cierre,
        private readonly TransicionadorDeExpediente $transicionador,
    ) {}

    /**
     * @throws ExpedienteNoEncontrado
     * @throws CierreNoProcede
     * @throws EntrevistaSinResolver
     * @throws EstadoNoPermitido
     */
    public function ejecutar(ContextoDeTenant $contexto, int $userId, int $id, int $version, ?string $clave): ResultadoDeSalida
    {
        $previa = $this->expedientes->porId($contexto->tenantId, $id) ?? throw new ExpedienteNoEncontrado;

        return $this->transicionador->ejecutar($contexto, $userId, $previa, $version, 'cierre', $clave, [], function (Expediente $actual, string $clave, string $huella) use ($userId) {
            $bloqueos = $this->cierre->bloqueos($actual);
            if (in_array(EvaluadorDeCierre::YA_CERRADO, $bloqueos, true)) {
                throw new EstadoNoPermitido('El cierre administrativo ya fue confirmado.');
            }
            if ($bloqueos === [EvaluadorDeCierre::ENTREVISTA_SIN_RESOLVER]) {
                throw new EntrevistaSinResolver;
            }
            if ($bloqueos !== []) {
                throw new CierreNoProcede(bloqueos: $bloqueos);
            }

            return $this->expedientes->actualizar($actual, [
                'cierre_estado' => 'Cerrado',
                'cierre_por' => $userId,
                'cierre_en' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
            ], 'CierreConfirmado', $userId, [], $clave, $huella);
        });
    }
}
