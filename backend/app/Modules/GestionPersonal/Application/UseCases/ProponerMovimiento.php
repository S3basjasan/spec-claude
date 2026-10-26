<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\ResultadoDeActuacion;
use App\Modules\GestionPersonal\Application\Services\PresentadorDeActuacion;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeSolicitante;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDeActuacion;

/** El Administrador propone un ascenso o traslado; la propuesta no modifica ninguna asignación. */
final class ProponerMovimiento
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
    public function ejecutar(ContextoDeTenant $contexto, int $userId, int $colaboradorId, mixed $tipo, array $datos, ?string $clave): ResultadoDeActuacion
    {
        $resuelto = is_string($tipo) ? TipoDeActuacion::tryFrom($tipo) : null;
        if ($resuelto === null || ! $resuelto->esMovimiento()) {
            throw new DatosInvalidos(['tipo' => ['El tipo debe ser Ascenso o Traslado.']]);
        }

        $solicitante = $this->solicitantes->administrador($contexto, $userId, $colaboradorId);

        return $this->presentador->presentar($contexto, $solicitante, $resuelto, $datos, $clave);
    }
}
