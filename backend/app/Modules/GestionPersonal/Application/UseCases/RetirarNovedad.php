<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeActuacion;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeSolicitante;
use App\Modules\GestionPersonal\Application\Services\TransicionadorDeActuacion;
use App\Modules\GestionPersonal\Domain\Entities\Actuacion;
use App\Modules\GestionPersonal\Domain\Exceptions\EstadoNoResoluble;
use App\Modules\GestionPersonal\Domain\Exceptions\SolicitudNoEncontrada;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeActuacion;

/**
 * Retiro de una novedad por su solicitante mientras esté Presentada o En revisión: queda «Retirada» con constancia
 * de quién y cuándo, no se borra y deja de ser resoluble. Compite con la resolución bajo el mismo bloqueo.
 */
final class RetirarNovedad
{
    public function __construct(
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly ResolvedorDeSolicitante $solicitantes,
        private readonly TransicionadorDeActuacion $transicionador,
    ) {}

    /** El Colaborador retira una novedad de su ficha, la haya presentado él o un Administrador en su nombre. */
    public function comoColaborador(ContextoDeTenant $contexto, int $userId, int $id, int $version, ?string $clave): ResultadoDeActuacion
    {
        $solicitante = $this->solicitantes->colaborador($contexto, $userId);
        $previa = $this->actuaciones->porId($contexto->tenantId, $id);
        if ($previa === null || $previa->colaboradorId !== $solicitante->colaboradorId) {
            throw new SolicitudNoEncontrada;
        }

        return $this->retirar($contexto, $userId, $previa, $version, $clave);
    }

    /** El Administrador retira solo lo que él mismo registró en nombre del colaborador. */
    public function comoAdministrador(ContextoDeTenant $contexto, int $userId, int $id, int $version, ?string $clave): ResultadoDeActuacion
    {
        $previa = $this->actuaciones->porId($contexto->tenantId, $id) ?? throw new SolicitudNoEncontrada;
        if ($previa->presentadaPor !== $userId) {
            throw new EstadoNoResoluble('Solo quien presentó la solicitud puede retirarla.');
        }

        return $this->retirar($contexto, $userId, $previa, $version, $clave);
    }

    private function retirar(ContextoDeTenant $contexto, int $userId, Actuacion $previa, int $version, ?string $clave): ResultadoDeActuacion
    {
        if (! $previa->tipo->esSolicitudDeNovedad()) {
            throw new EstadoNoResoluble('Solo se retiran permisos, licencias y vacaciones.');
        }

        return $this->transicionador->ejecutar($contexto, $userId, $previa, $version, 'retiro', $clave, [], fn (Actuacion $actual) => [
            'nuevo' => EstadoDeActuacion::Retirada,
            'evento' => 'Retirada',
            'justificacion' => null,
            'detalle' => [],
            'novedad' => [],
            'movimiento' => [],
        ]);
    }
}
