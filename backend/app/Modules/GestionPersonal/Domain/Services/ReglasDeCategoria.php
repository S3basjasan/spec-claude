<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeCategoria;

/**
 * Reglas puras de una categoría documental. La unicidad del nombre la decide la restricción de la base de datos.
 */
final class ReglasDeCategoria
{
    public const LIMITE_NOMBRE = 100;

    /**
     * Recorta el nombre; vacío tras recortar equivale a ausente.
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
    public function errores(array $final): array
    {
        $nombre = $final['nombre'] ?? null;

        if ($nombre === null || $nombre === '') {
            return ['nombre' => ['Este campo es obligatorio.']];
        }

        if (mb_strlen((string) $nombre) > self::LIMITE_NOMBRE) {
            return ['nombre' => ['No puede superar los '.self::LIMITE_NOMBRE.' caracteres.']];
        }

        return [];
    }

    /** @return array<string, list<string>> */
    public function erroresEstado(mixed $estado): array
    {
        if (! is_string($estado) || ! in_array($estado, EstadoDeCategoria::valores(), true)) {
            return ['estado' => ['El estado no es válido.']];
        }

        return [];
    }
}
