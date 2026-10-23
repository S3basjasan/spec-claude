<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeDetalleDePdi;
use App\Modules\Desarrollo\Application\Services\ConstructorDePdi;
use App\Modules\Desarrollo\Application\Services\ValidadorDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\PlanDuplicadoPorTipo;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;

/**
 * Crea un plan en Borrador con sus compromisos, acciones e hitos. Puede guardarse incompleto: los requisitos de
 * completitud se exigen al confirmar el acuerdo del responsable (FR-026).
 */
final class CrearPdi
{
    public function __construct(
        private readonly ConstructorDePdi $constructor,
        private readonly ValidadorDePdi $validador,
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly ArmadorDeDetalleDePdi $armador,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function ejecutar(int $tenantId, int $actorId, array $datos): array
    {
        $tipo = TipoDePdi::from($datos['tipo']);
        $colaboradorId = (int) $datos['colaborador_id'];

        $this->validador->validarColaboradorVigente($tenantId, $actorId, $colaboradorId);
        $this->validador->validarCabecera($tenantId, $tipo, $datos);

        $existente = $this->repositorio->planAbierto($tenantId, $colaboradorId, $tipo);
        if ($existente !== null) {
            throw new PlanDuplicadoPorTipo($existente['codigo'], $existente['id']);
        }

        $pdi = $this->constructor->deDatos($tenantId, $actorId, $tipo, $colaboradorId, $datos);
        $this->validador->validarEstructura($pdi);

        return $this->armador->detalle($this->repositorio->crear($pdi, $actorId), true, $actorId);
    }
}
