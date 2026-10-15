<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CadenaDeTiposDTO;
use App\Modules\Estructura\Domain\Exceptions\CadenaInvalida;
use App\Modules\Estructura\Domain\Exceptions\CadenaModificada;
use App\Modules\Estructura\Domain\Repositories\TipoUnidadRepositoryInterface;
use App\Modules\Estructura\Domain\Services\CadenaDeTipos;
use App\Modules\Estructura\Domain\ValueObjects\BorradorDeTipo;

final class GuardarCadenaDeTipos
{
    public function __construct(
        private readonly TipoUnidadRepositoryInterface $tipos,
        private readonly CadenaDeTipos $cadena,
    ) {}

    /**
     * Aplica renombres y reordenamiento en una sola operación. Se rechaza (sin cambios) si la cadena cambió, si la lista
     * no es válida o si alguna unidad quedaría bajo una unidad padre de nivel igual o más profundo.
     *
     * @param  array<int, BorradorDeTipo>  $borrador  La lista completa en el nuevo orden.
     *
     * @throws CadenaModificada
     * @throws CadenaInvalida
     */
    public function ejecutar(string $version, array $borrador, ?int $actorId = null): CadenaDeTiposDTO
    {
        return CadenaDeTiposDTO::desde($this->tipos->guardarCadena($version, $borrador, $actorId), $this->cadena);
    }
}
