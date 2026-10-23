<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Entities\CompromisoDePdi;
use App\Modules\Desarrollo\Domain\Entities\HitoDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\DatosInvalidosDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\PeriodoDePdi;

/**
 * Reglas estructurales de compromisos, acciones e hitos de un plan (FR-009 a FR-017, FR-024). Las reglas que
 * necesitan otros módulos (elemento activo, vínculo, administrador) las aplica la capa de aplicación con sus puertos.
 */
final class ReglasDeCompromiso
{
    private const NIVELES = ['A', 'B', 'C', 'D'];

    /**
     * @param  list<CompromisoDePdi>  $compromisos
     * @param  list<HitoDePdi>  $hitos
     * @param  list<DimensionDeCompromiso>  $dimensionesDisponibles
     *
     * @throws DatosInvalidosDePdi con los errores por campo (`compromisos.0.dimension`, `hitos.1.fecha`…)
     */
    public function validar(PeriodoDePdi $periodo, array $compromisos, array $hitos, array $dimensionesDisponibles): void
    {
        $errores = [];
        $vistos = [];

        foreach ($compromisos as $i => $c) {
            $p = "compromisos.{$i}";
            if (! in_array($c->dimension, $dimensionesDisponibles, true)) {
                $errores["{$p}.dimension"][] = $c->dimension === DimensionDeCompromiso::Conocimiento
                    ? 'La dimensión Conocimiento no está disponible.'
                    : 'La dimensión no está disponible.';
            }

            if ($c->dimension->llevaElemento()) {
                if ($c->elementoId === null) {
                    $errores["{$p}.elemento_id"][] = 'El elemento es obligatorio.';
                } else {
                    $clave = $c->claveDeElemento();
                    if (isset($vistos[$clave])) {
                        $errores["{$p}.elemento_id"][] = 'El elemento ya está en otro compromiso del plan.';
                    }
                    $vistos[$clave] = true;
                }
            } elseif ($c->descripcion === null || trim($c->descripcion) === '') {
                $errores["{$p}.descripcion"][] = 'La descripción es obligatoria.';
            }

            $this->validarNiveles($c, $p, $errores);

            if ($c->fechaFin < $c->fechaInicio) {
                $errores["{$p}.fecha_fin"][] = 'La fecha de fin no puede ser anterior a la de inicio.';
            }
            if (! $periodo->contiene($c->fechaInicio) || ! $periodo->contiene($c->fechaFin)) {
                $errores["{$p}.fecha_inicio"][] = 'Las fechas del compromiso deben estar dentro del periodo del plan.';
            }

            foreach ($c->acciones as $j => $a) {
                $q = "{$p}.acciones.{$j}";
                if (trim($a->descripcion) === '') {
                    $errores["{$q}.descripcion"][] = 'La descripción es obligatoria.';
                }
                if ($a->fechaFin < $a->fechaInicio) {
                    $errores["{$q}.fecha_fin"][] = 'La fecha de fin no puede ser anterior a la de inicio.';
                }
                if (! $periodo->contiene($a->fechaInicio) || ! $periodo->contiene($a->fechaFin)) {
                    $errores["{$q}.fecha_inicio"][] = 'Las fechas de la acción deben estar dentro del periodo del plan.';
                }
                if ($a->planAccionId !== null && ! $a->tipo->esVinculable()) {
                    $errores["{$q}.plan_accion_id"][] = 'Solo las acciones de tipo Curso, Mentoring o Codesarrollo se pueden vincular.';
                }
            }
        }

        foreach ($hitos as $k => $h) {
            if (trim($h->descripcion) === '') {
                $errores["hitos.{$k}.descripcion"][] = 'La descripción es obligatoria.';
            }
            if (! $periodo->contiene($h->fecha)) {
                $errores["hitos.{$k}.fecha"][] = 'La fecha del hito debe estar dentro del periodo del plan.';
            }
        }

        if ($errores !== []) {
            throw DatosInvalidosDePdi::conErrores($errores);
        }
    }

    /**
     * @param  array<string, list<string>>  $errores
     */
    private function validarNiveles(CompromisoDePdi $c, string $p, array &$errores): void
    {
        if (! $c->dimension->llevaElemento()) {
            if ($c->nivelActual !== null || $c->nivelObjetivo !== null) {
                $errores["{$p}.nivel_actual"][] = 'Los niveles solo aplican a competencias y conocimientos.';
            }

            return;
        }

        foreach (['nivel_actual' => $c->nivelActual, 'nivel_objetivo' => $c->nivelObjetivo] as $campo => $nivel) {
            if ($nivel !== null && ! in_array($nivel, self::NIVELES, true)) {
                $errores["{$p}.{$campo}"][] = 'El nivel debe ser A, B, C o D.';
            }
        }
        if (($c->nivelActual === null) !== ($c->nivelObjetivo === null)) {
            $errores["{$p}.nivel_objetivo"][] = 'Indica el nivel actual y el nivel a alcanzar.';
        } elseif ($c->nivelActual !== null && $c->nivelActual === $c->nivelObjetivo) {
            $errores["{$p}.nivel_objetivo"][] = 'El nivel a alcanzar debe ser distinto del actual.';
        }
    }
}
