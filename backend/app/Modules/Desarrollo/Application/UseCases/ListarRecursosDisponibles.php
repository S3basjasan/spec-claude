<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\RecursoDTO;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoRecurso;

/** Recursos disponibles para asociar (selectores "Asociar curso" y "Asociar contenido"). */
final class ListarRecursosDisponibles
{
    public function __construct(private readonly CatalogoFormativoRepositoryInterface $repositorio) {}

    /**
     * @return array<int, RecursoDTO>
     */
    public function ejecutar(TipoRecurso $tipo, ?string $q, ?int $competenciaId): array
    {
        return array_map(
            fn ($r) => RecursoDTO::desde($r, true),
            $this->repositorio->recursosDisponibles($tipo, $q, $competenciaId),
        );
    }
}
