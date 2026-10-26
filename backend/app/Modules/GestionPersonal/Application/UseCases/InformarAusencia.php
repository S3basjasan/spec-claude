<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeActuacion;
use App\Modules\GestionPersonal\Application\Services\PresentadorDeActuacion;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeSolicitante;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDeActuacion;

/** El Administrador informa una ausencia ya ocurrida: un hecho, no una autorización. */
final class InformarAusencia
{
    public function __construct(
        private readonly ResolvedorDeSolicitante $solicitantes,
        private readonly PresentadorDeActuacion $presentador,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public function ejecutar(ContextoDeTenant $contexto, int $userId, int $colaboradorId, array $datos, ?string $clave): ResultadoDeActuacion
    {
        $solicitante = $this->solicitantes->administrador($contexto, $userId, $colaboradorId);

        return $this->presentador->presentar($contexto, $solicitante, TipoDeActuacion::Ausencia, $datos, $clave);
    }
}
