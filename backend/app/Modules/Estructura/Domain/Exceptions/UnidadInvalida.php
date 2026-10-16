<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

/**
 * La unidad incumple una regla que depende de datos (unidad padre, árbol, cadena). Lleva un mensaje por campo.
 */
final class UnidadInvalida extends RuntimeException
{
    /**
     * @param  array<string, string>  $errores  Campo → mensaje.
     */
    public function __construct(public readonly array $errores)
    {
        parent::__construct((string) (reset($errores) ?: 'La unidad no es válida.'));
    }

    public static function padreObligatoria(): self
    {
        return new self(['unidad_padre_id' => 'Selecciona la unidad padre.']);
    }

    public static function padreInexistente(): self
    {
        return new self(['unidad_padre_id' => 'La unidad padre no existe.']);
    }

    public static function padreInactivo(): self
    {
        return new self(['unidad_padre_id' => 'La unidad padre debe estar activa.']);
    }

    public static function padreEsLaMisma(): self
    {
        return new self(['unidad_padre_id' => 'Una unidad no puede ser su propia unidad padre.']);
    }

    public static function padreEsDependiente(): self
    {
        return new self(['unidad_padre_id' => 'La unidad padre no puede ser una de sus unidades dependientes.']);
    }

    public static function sinSubunidades(): self
    {
        return new self(['unidad_padre_id' => 'Este tipo no admite subunidades.']);
    }

    public static function padreDeOtroTipo(): self
    {
        return new self(['unidad_padre_id' => 'La unidad padre debe ser del tipo inmediatamente superior al de la unidad.']);
    }

    public static function reubicacionRompeJerarquia(int $cantidad): self
    {
        return new self(['unidad_padre_id' => $cantidad === 1
            ? 'No se puede reubicar la unidad: dejaría 1 posición reportando a otra de una unidad inferior o de otra rama.'
            : "No se puede reubicar la unidad: dejaría $cantidad posiciones reportando a otra de una unidad inferior o de otra rama."]);
    }

    public static function raizNoReubicable(): self
    {
        return new self(['unidad_padre_id' => 'La unidad raíz no puede tener unidad padre.']);
    }

    public static function raizNoDesactivable(): self
    {
        return new self(['estado' => 'La unidad raíz no se puede desactivar.']);
    }
}
