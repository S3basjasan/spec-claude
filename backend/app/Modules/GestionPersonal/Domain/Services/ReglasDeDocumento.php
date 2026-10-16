<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\Exceptions\ArchivoInvalido;

/**
 * Reglas puras de un documento: título, categoría, visibilidad y tamaño del archivo.
 */
final class ReglasDeDocumento
{
    public const LIMITE_TITULO = 200;

    public const MAX_BYTES = 10000000;

    /**
     * Recorta el título; vacío tras recortar equivale a ausente.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function normalizar(array $datos): array
    {
        $salida = $datos;
        if (is_string($datos['titulo'] ?? null)) {
            $titulo = trim($datos['titulo']);
            $salida['titulo'] = $titulo === '' ? null : $titulo;
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $datos  Datos ya normalizados.
     * @return array<string, list<string>>
     */
    public function errores(array $datos): array
    {
        $errores = [];
        $titulo = $datos['titulo'] ?? null;

        if ($titulo === null || $titulo === '') {
            $errores['titulo'][] = 'Este campo es obligatorio.';
        } elseif (mb_strlen((string) $titulo) > self::LIMITE_TITULO) {
            $errores['titulo'][] = 'No puede superar los '.self::LIMITE_TITULO.' caracteres.';
        }

        $categoria = $datos['categoria_id'] ?? null;
        if (! is_int($categoria) || $categoria < 1) {
            $errores['categoria_id'][] = 'Selecciona una categoría.';
        }

        if (array_key_exists('visible_colaborador', $datos) && ! is_bool($datos['visible_colaborador'])) {
            $errores['visible_colaborador'][] = 'El valor no es válido.';
        }

        return $errores;
    }

    /** @throws ArchivoInvalido Vacío o mayor que 10 000 000 bytes (el máximo exacto se acepta). */
    public function validarTamano(int $bytes): void
    {
        if ($bytes <= 0) {
            throw new ArchivoInvalido('vacio');
        }
        if ($bytes > self::MAX_BYTES) {
            throw new ArchivoInvalido('demasiado_grande');
        }
    }

    /** Huella de la operación: ficha, actor, título normalizado, categoría, visibilidad y SHA-256 de los bytes. */
    public function hashDeOperacion(int $colaboradorId, int $actorId, string $titulo, int $categoriaId, bool $visible, string $sha256): string
    {
        return hash('sha256', (string) json_encode([
            'colaborador' => $colaboradorId,
            'actor' => $actorId,
            'titulo' => $titulo,
            'categoria' => $categoriaId,
            'visible' => $visible,
            'sha256' => $sha256,
        ], JSON_UNESCAPED_UNICODE));
    }
}
