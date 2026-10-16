<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoRecurso;

/**
 * Cursos disponibles del catálogo como punto de partida de una acción (FR-010). Se proponen tipo, formador, horas y
 * competencias; el administrador puede cambiarlos. Mixto no sugiere tipo.
 */
final class ListarCursosParaPlan
{
    public function __construct(private readonly CatalogoFormativoRepositoryInterface $catalogo) {}

    /**
     * @return array{data: list<array{id: int, nombre: string, tipo_sugerido: string|null, formador: string|null, duracion_horas: float, competencia_ids: list<int>}>}
     */
    public function ejecutar(?string $texto): array
    {
        $cursos = $this->catalogo->recursosDisponibles(TipoRecurso::Curso, $texto, null);
        $competencias = $this->catalogo->competenciasDeCursos(array_map(fn ($c) => $c->id, $cursos));

        return ['data' => array_map(fn ($c) => [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'tipo_sugerido' => match ($c->tipo) {
                'Online' => 'ELearning',
                'Presencial' => 'Presencial',
                default => null,
            },
            'formador' => $c->formador,
            'duracion_horas' => round($c->duracionMinutos / 60, 2),
            'competencia_ids' => $competencias[$c->id] ?? [],
        ], $cursos)];
    }
}
