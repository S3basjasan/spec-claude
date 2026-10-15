<?php

namespace App\Modules\Estructura\Domain\Repositories;

use App\Modules\Estructura\Domain\Entities\TipoUnidad;
use App\Modules\Estructura\Domain\Exceptions\CadenaInvalida;
use App\Modules\Estructura\Domain\Exceptions\CadenaModificada;
use App\Modules\Estructura\Domain\ValueObjects\BorradorDeTipo;

/**
 * Puerto de persistencia de la cadena de tipos de unidad. Los tipos nunca se eliminan.
 */
interface TipoUnidadRepositoryInterface
{
    /**
     * La cadena ordenada por nivel, con la cantidad de unidades de cada tipo.
     *
     * @return array<int, TipoUnidad>
     */
    public function todos(): array;

    public function obtener(int $id): ?TipoUnidad;

    /**
     * Agrega un tipo como último nivel.
     *
     * @throws CadenaInvalida
     */
    public function crear(string $nombre, ?int $actorId): TipoUnidad;

    /**
     * Aplica renombres y reordenamiento en una sola operación; sin cambios no escribe. El nivel de cada tipo es su
     * posición en `$borrador`.
     *
     * @param  array<int, BorradorDeTipo>  $borrador
     * @return array<int, TipoUnidad> La cadena resultante.
     *
     * @throws CadenaModificada
     * @throws CadenaInvalida
     */
    public function guardarCadena(string $version, array $borrador, ?int $actorId): array;
}
