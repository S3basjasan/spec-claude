<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\Ports\CatalogoDeCategoriasPort;
use App\Modules\GestionPersonal\Domain\Exceptions\InformacionNoDisponible;

/** Nombre vigente de las categorías de un conjunto de documentos, en una sola lectura y sin ocultar un fallo. */
final class ResolvedorDeCategoriasDeDocumentos
{
    public function __construct(private readonly CatalogoDeCategoriasPort $catalogo) {}

    /**
     * @param  list<int>  $ids
     * @return array<int, array{id: int, nombre: string, estado: string}>
     *
     * @throws InformacionNoDisponible
     */
    public function porIds(int $tenantId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $lectura = $this->catalogo->porIds($tenantId, array_values(array_unique($ids)));

        if (! $lectura->estaDisponible()) {
            throw new InformacionNoDisponible;
        }

        return $lectura->datos;
    }
}
