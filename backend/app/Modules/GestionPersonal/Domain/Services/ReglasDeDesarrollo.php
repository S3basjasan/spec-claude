<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeFormacion;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeMovilidad;
use DateTimeImmutable;

/**
 * Reglas puras de formación, experiencia y movilidad, evaluadas sobre el estado resultante completo.
 */
final class ReglasDeDesarrollo
{
    private const LIMITES_FORMACION = ['nivel' => 100, 'titulo_programa' => 200, 'institucion' => 200, 'observaciones' => 2000];

    private const LIMITES_EXPERIENCIA = ['organizacion' => 200, 'cargo' => 200, 'funciones' => 2000, 'observaciones' => 2000];

    private const OBLIGATORIOS_FORMACION = ['nivel', 'titulo_programa', 'institucion', 'estado'];

    private const OBLIGATORIOS_EXPERIENCIA = ['organizacion', 'cargo', 'fecha_inicio'];

    /**
     * Recorta textos y convierte vacíos a null.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function normalizar(array $datos): array
    {
        $salida = [];
        foreach ($datos as $campo => $valor) {
            if (is_string($valor)) {
                $valor = trim($valor);
                $valor = $valor === '' ? null : $valor;
            }
            $salida[$campo] = $valor;
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $final  Estado final normalizado.
     * @return array<string, list<string>>
     */
    public function erroresFormacion(array $final, string $hoy): array
    {
        $errores = $this->erroresComunes($final, self::OBLIGATORIOS_FORMACION, self::LIMITES_FORMACION);

        if (($final['estado'] ?? null) !== null && ! in_array($final['estado'], EstadoDeFormacion::valores(), true)) {
            $errores['estado'][] = 'El estado de la formación no es válido.';
        }

        return $this->erroresDeFechas($errores, $final, $hoy, false);
    }

    /**
     * @param  array<string, mixed>  $final  Estado final normalizado.
     * @return array<string, list<string>>
     */
    public function erroresExperiencia(array $final, string $hoy): array
    {
        $errores = $this->erroresComunes($final, self::OBLIGATORIOS_EXPERIENCIA, self::LIMITES_EXPERIENCIA);

        return $this->erroresDeFechas($errores, $final, $hoy, true);
    }

    /**
     * @param  array<string, mixed>  $final
     * @return array<string, list<string>>
     */
    public function erroresMovilidad(array $final): array
    {
        $errores = [];

        if (! isset($final['estado']) || ! in_array($final['estado'], EstadoDeMovilidad::valores(), true)) {
            $errores['estado'][] = 'El estado de movilidad no es válido.';
        }

        if (isset($final['observaciones']) && mb_strlen((string) $final['observaciones']) > 2000) {
            $errores['observaciones'][] = 'No puede superar los 2000 caracteres.';
        }

        return $errores;
    }

    /**
     * @param  array<string, mixed>  $final
     * @param  list<string>  $obligatorios
     * @param  array<string, int>  $limites
     * @return array<string, list<string>>
     */
    private function erroresComunes(array $final, array $obligatorios, array $limites): array
    {
        $errores = [];

        foreach ($obligatorios as $campo) {
            if (! isset($final[$campo]) || $final[$campo] === '') {
                $errores[$campo][] = 'Este campo es obligatorio.';
            }
        }

        foreach ($limites as $campo => $limite) {
            if (isset($final[$campo]) && mb_strlen((string) $final[$campo]) > $limite) {
                $errores[$campo][] = "No puede superar los $limite caracteres.";
            }
        }

        return $errores;
    }

    /**
     * @param  array<string, list<string>>  $errores
     * @param  array<string, mixed>  $final
     * @return array<string, list<string>>
     */
    private function erroresDeFechas(array $errores, array $final, string $hoy, bool $finNoFutura): array
    {
        $inicio = $final['fecha_inicio'] ?? null;
        $fin = $final['fecha_finalizacion'] ?? null;
        $inicioValido = false;
        $finValido = false;

        if ($inicio !== null) {
            if (! $this->esFechaReal((string) $inicio)) {
                $errores['fecha_inicio'][] = 'La fecha de inicio no es una fecha válida (AAAA-MM-DD).';
            } else {
                $inicioValido = true;
                if ($inicio > $hoy) {
                    $errores['fecha_inicio'][] = 'La fecha de inicio no puede ser futura.';
                }
            }
        }

        if ($fin !== null) {
            if (! $this->esFechaReal((string) $fin)) {
                $errores['fecha_finalizacion'][] = 'La fecha de finalización no es una fecha válida (AAAA-MM-DD).';
            } else {
                $finValido = true;
                if ($finNoFutura && $fin > $hoy) {
                    $errores['fecha_finalizacion'][] = 'La fecha de finalización no puede ser futura.';
                }
            }
        }

        if ($inicioValido && $finValido && $fin < $inicio) {
            $errores['fecha_finalizacion'][] = 'La fecha de finalización no puede ser anterior a la de inicio.';
        }

        return $errores;
    }

    private function esFechaReal(string $fecha): bool
    {
        $parseada = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $parseada !== false && $parseada->format('Y-m-d') === $fecha;
    }
}
