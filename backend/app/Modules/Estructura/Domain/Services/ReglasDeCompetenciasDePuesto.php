<?php

namespace App\Modules\Estructura\Domain\Services;

use App\Modules\Estructura\Domain\Exceptions\CompetenciasDePuestoInvalidas;
use App\Modules\Estructura\Domain\ValueObjects\AsignacionDeCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaConGrados;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;

/**
 * Reglas de las competencias de un puesto que dependen de los datos del diccionario de competencias:
 *
 * - Una competencia nueva debe existir y estar activa, y su grado debe ser uno de los de esa competencia.
 * - Una competencia inactiva solo se acepta si el puesto ya la tenía asignada y con el mismo grado
 *   (se mantiene tal como está o se quita; no se cambia su grado ni se agregan otras inactivas).
 *
 * Es una regla pura de dominio: no depende de Laravel ni de Eloquent.
 */
final class ReglasDeCompetenciasDePuesto
{
    public const NO_DISPONIBLE = 'La competencia no existe o no está activa.';

    public const GRADO_INEXISTENTE = 'Ese grado no existe para la competencia.';

    public const GRADO_BLOQUEADO = 'La competencia está inactiva: su grado no se puede cambiar. Quítala o déjala como estaba.';

    /**
     * @param  array<int, AsignacionDeCompetencia>  $solicitadas  Las competencias que se quieren guardar, en orden.
     * @param  array<int, CompetenciaConGrados>  $existentes  Competencias del diccionario por id (activas e inactivas).
     * @param  array<int, CompetenciaDePuesto>  $asignadasActuales  Las que el puesto ya tiene (vacío al crear).
     *
     * @throws CompetenciasDePuestoInvalidas Con un error por cada fila no válida.
     */
    public function validar(array $solicitadas, array $existentes, array $asignadasActuales = []): void
    {
        $gradosActuales = [];
        foreach ($asignadasActuales as $asignada) {
            $gradosActuales[$asignada->competencia->id] = $asignada->gradoRequerido;
        }

        $errores = [];
        foreach (array_values($solicitadas) as $indice => $asignacion) {
            $competencia = $existentes[$asignacion->competenciaId] ?? null;

            if ($competencia === null) {
                $errores["competencias.$indice.competencia_id"] = self::NO_DISPONIBLE;

                continue;
            }

            if ($competencia->competencia->estado !== EstadoCompetencia::Activo) {
                $yaAsignada = array_key_exists($asignacion->competenciaId, $gradosActuales);

                if (! $yaAsignada) {
                    $errores["competencias.$indice.competencia_id"] = self::NO_DISPONIBLE;
                } elseif ($gradosActuales[$asignacion->competenciaId] !== $asignacion->grado) {
                    $errores["competencias.$indice.grado_requerido"] = self::GRADO_BLOQUEADO;
                }

                continue;
            }

            if (! $competencia->tieneGrado($asignacion->grado)) {
                $errores["competencias.$indice.grado_requerido"] = self::GRADO_INEXISTENTE;
            }
        }

        if ($errores !== []) {
            throw new CompetenciasDePuestoInvalidas($errores);
        }
    }
}
