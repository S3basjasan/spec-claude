<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

use DateTimeImmutable;

/**
 * Datos del tenant que consumen los reportes, tal como los entregan las fuentes de otras features, sin filtrar.
 * Los filtros y el período se aplican después, en los calculadores, para evaluar el período y el anterior con una
 * sola lectura. Una fuente que no respondió figura en `noDisponibles` y su colección queda vacía.
 */
final class DatosDeReportes
{
    public const FUENTE_PLAN = 'plan';

    public const FUENTE_ACTIVIDADES = 'actividades';

    public const FUENTE_MICROLEARNING = 'microlearning';

    public const FUENTE_DNF = 'dnf';

    public const FUENTE_PERSONAL = 'personal';

    /**
     * @param  array<int, array<string, mixed>>  $acciones  accion_id => acción del plan (con su estado calculado)
     * @param  list<array<string, mixed>>  $actividades  actividades con estado, horas, inscritos y promedio
     * @param  list<array<string, mixed>>  $asistencias  registros de asistencia (solo participantes con registro)
     * @param  list<array<string, mixed>>  $completaciones  micro-contenidos completados
     * @param  list<array{actividad_id: int, colaborador_id: int, calificacion: int}>  $encuestas
     * @param  array<int, float>  $beneficios  accion_id => beneficio estimado de la evaluación de impacto
     * @param  list<array<string, mixed>>  $evaluaciones  estado y niveles por competencia y DNF, ordenadas por fecha
     * @param  array<int, int|null>  $departamentoDe  colaborador_id => unidad actual
     * @param  list<string>  $noDisponibles  fuentes que no respondieron
     */
    public function __construct(
        public readonly DateTimeImmutable $hoy,
        public readonly array $acciones,
        public readonly array $actividades,
        public readonly array $asistencias,
        public readonly array $completaciones,
        public readonly array $encuestas,
        public readonly array $beneficios,
        public readonly array $evaluaciones,
        public readonly array $departamentoDe,
        public readonly array $noDisponibles = [],
    ) {}

    public function disponible(string ...$fuentes): bool
    {
        foreach ($fuentes as $fuente) {
            if (in_array($fuente, $this->noDisponibles, true)) {
                return false;
            }
        }

        return true;
    }

    public function departamentoDe(int $colaboradorId): ?int
    {
        return $this->departamentoDe[$colaboradorId] ?? null;
    }
}
