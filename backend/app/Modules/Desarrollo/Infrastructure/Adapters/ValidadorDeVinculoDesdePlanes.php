<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\AccionesVinculablesInterface;
use App\Modules\Desarrollo\Application\Ports\ValidadorDeVinculoInterface;
use App\Modules\Desarrollo\Domain\Exceptions\VinculoInvalido;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeAccionPdi;

/** Valida el tipo de la acción del PDI y que la acción del plan esté entre las vinculables del colaborador. */
final class ValidadorDeVinculoDesdePlanes implements ValidadorDeVinculoInterface
{
    public function __construct(private readonly AccionesVinculablesInterface $vinculables) {}

    public function validar(int $tenantId, int $colaboradorId, TipoDeAccionPdi $tipo, int $planAccionId): void
    {
        if (! $tipo->esVinculable()) {
            throw VinculoInvalido::tipoNoVinculable();
        }

        $ids = array_map(fn (AccionDeActividad $a) => $a->accionId, $this->vinculables->deColaborador($tenantId, $colaboradorId));
        if (! in_array($planAccionId, $ids, true)) {
            throw VinculoInvalido::noDisponible();
        }
    }
}
