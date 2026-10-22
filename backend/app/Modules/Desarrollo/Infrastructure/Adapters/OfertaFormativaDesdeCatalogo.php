<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\OfertaFormativaInterface;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;

/** Oferta formativa de una competencia según el Catálogo Formativo (feature 005), del mismo módulo. */
final class OfertaFormativaDesdeCatalogo implements OfertaFormativaInterface
{
    public function __construct(private readonly CatalogoFormativoRepositoryInterface $catalogo) {}

    public function tieneOferta(int $competenciaId): bool
    {
        return $this->catalogo->configuracionDe($competenciaId)->recursosDisponibles() >= 1;
    }
}
