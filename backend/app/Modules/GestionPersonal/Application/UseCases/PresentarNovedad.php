<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeActuacion;
use App\Modules\GestionPersonal\Application\Services\PresentadorDeActuacion;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeSolicitante;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDeActuacion;

/** Presenta un permiso, una licencia o unas vacaciones: propia (Colaborador) o en nombre del colaborador (Administrador). */
final class PresentarNovedad
{
    public function __construct(
        private readonly ResolvedorDeSolicitante $solicitantes,
        private readonly PresentadorDeActuacion $presentador,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws DatosInvalidos
     */
    public function comoAdministrador(ContextoDeTenant $contexto, int $userId, int $colaboradorId, mixed $tipo, array $datos, ?string $clave): ResultadoDeActuacion
    {
        $solicitante = $this->solicitantes->administrador($contexto, $userId, $colaboradorId);

        return $this->presentador->presentar($contexto, $solicitante, $this->tipo($tipo), $datos, $clave);
    }

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws DatosInvalidos
     */
    public function comoColaborador(ContextoDeTenant $contexto, int $userId, mixed $tipo, array $datos, ?string $clave): ResultadoDeActuacion
    {
        $solicitante = $this->solicitantes->colaborador($contexto, $userId);

        return $this->presentador->presentar($contexto, $solicitante, $this->tipo($tipo), $datos, $clave);
    }

    private function tipo(mixed $tipo): TipoDeActuacion
    {
        $resuelto = is_string($tipo) ? TipoDeActuacion::tryFrom($tipo) : null;
        if ($resuelto === null || ! $resuelto->esSolicitudDeNovedad()) {
            throw new DatosInvalidos(['tipo' => ['El tipo debe ser Permiso, Licencia o Vacaciones.']]);
        }

        return $resuelto;
    }
}
