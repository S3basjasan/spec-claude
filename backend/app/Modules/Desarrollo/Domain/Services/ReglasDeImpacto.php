<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Exceptions\ImpactoInvalido;
use App\Modules\Desarrollo\Domain\Exceptions\ImpactoNoPermitido;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;

/** Reglas de la evaluación de impacto de una acción: solo Finalizada (aunque el plan esté Cerrado) y datos completos. */
final class ReglasDeImpacto
{
    private const MAX_INDICADOR = 150;

    private const MAX_UNIDAD = 50;

    private const MAX_JUSTIFICACION = 2000;

    public function validarAccion(EstadoAccion $estado): void
    {
        if ($estado !== EstadoAccion::Finalizada) {
            throw new ImpactoNoPermitido;
        }
    }

    /**
     * @param  array<string, mixed>  $datos  indicador, unidad, valor_antes, valor_despues, beneficio_estimado, justificacion
     */
    public function validar(array $datos): void
    {
        $errores = [];
        $texto = function (string $campo, int $max, string $etiqueta) use (&$errores, $datos): void {
            $valor = $datos[$campo] ?? null;
            if (! is_string($valor) || trim($valor) === '') {
                $errores[$campo][] = "{$etiqueta} es obligatorio.";
            } elseif (mb_strlen($valor) > $max) {
                $errores[$campo][] = "{$etiqueta} no puede superar {$max} caracteres.";
            }
        };
        $numero = function (string $campo, string $etiqueta) use (&$errores, $datos): void {
            $valor = $datos[$campo] ?? null;
            if ($valor === null || $valor === '' || ! is_numeric($valor)) {
                $errores[$campo][] = "{$etiqueta} debe ser un número.";
            }
        };

        $texto('indicador', self::MAX_INDICADOR, 'El indicador de negocio');
        $texto('unidad', self::MAX_UNIDAD, 'La unidad');
        $texto('justificacion', self::MAX_JUSTIFICACION, 'La justificación');
        $numero('valor_antes', 'El valor antes');
        $numero('valor_despues', 'El valor después');
        $numero('beneficio_estimado', 'El beneficio estimado');
        if (! isset($errores['beneficio_estimado']) && (float) $datos['beneficio_estimado'] < 0) {
            $errores['beneficio_estimado'][] = 'El beneficio estimado no puede ser negativo.';
        }

        if ($errores !== []) {
            throw new ImpactoInvalido($errores);
        }
    }
}
