<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeSalida;
use App\Modules\GestionPersonal\Application\Services\TransicionadorDeExpediente;
use App\Modules\GestionPersonal\Domain\Entities\Expediente;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EstadoNoPermitido;
use App\Modules\GestionPersonal\Domain\Exceptions\ExpedienteNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeSalida;
use Illuminate\Support\Carbon;

/** Cancela una salida que todavía no se aplicó. Una salida efectiva no se cancela: su corrección es otra feature. */
final class CancelarSalida
{
    public function __construct(
        private readonly ExpedienteRepositoryInterface $expedientes,
        private readonly TransicionadorDeExpediente $transicionador,
    ) {}

    /**
     * @throws ExpedienteNoEncontrado
     * @throws DatosInvalidos
     * @throws EstadoNoPermitido
     */
    public function ejecutar(ContextoDeTenant $contexto, int $userId, int $id, int $version, mixed $razon, ?string $clave): ResultadoDeSalida
    {
        $previa = $this->expedientes->porId($contexto->tenantId, $id) ?? throw new ExpedienteNoEncontrado;
        $texto = is_string($razon) ? trim($razon) : '';
        if ($texto === '' || mb_strlen($texto) > ReglasDeSalida::RAZON_MAXIMA) {
            throw new DatosInvalidos(['razon' => ['La razón es obligatoria y no puede superar '.ReglasDeSalida::RAZON_MAXIMA.' caracteres.']]);
        }

        return $this->transicionador->ejecutar($contexto, $userId, $previa, $version, 'cancelar', $clave, [$texto], function (Expediente $actual, string $clave, string $huella) use ($userId, $texto) {
            if (! $actual->estado->esPendienteDeAplicar()) {
                throw new EstadoNoPermitido('Solo una salida que todavía no se aplicó puede cancelarse.');
            }

            return $this->expedientes->actualizar($actual, [
                'estado' => 'Cancelada',
                'cancelacion_razon' => $texto,
                'cancelacion_por' => $userId,
                'cancelacion_en' => Carbon::now('UTC')->format('Y-m-d H:i:s'),
                'incidencia_codigo' => null,
                'incidencia_detalle' => null,
            ], 'Cancelada', $userId, ['razon' => $texto], $clave, $huella);
        });
    }
}
