<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use App\Modules\Estructura\Domain\ValueObjects\UnidadAfectadaPorCadena;
use RuntimeException;

/**
 * La cadena de tipos (o un tipo nuevo) incumple una regla: errores por campo o unidades que quedarían en conflicto.
 */
final class CadenaInvalida extends RuntimeException
{
    /**
     * @param  array<string, string>  $errores  Clave (`nombre`, `tipos.2.nombre`, `tipos`) → mensaje.
     * @param  array<int, UnidadAfectadaPorCadena>  $unidadesInvalidas
     */
    public function __construct(public readonly array $errores = [], public readonly array $unidadesInvalidas = [])
    {
        parent::__construct($unidadesInvalidas !== []
            ? sprintf('El nuevo orden dejaría %d %s bajo una unidad padre de nivel igual o más profundo.', count($unidadesInvalidas), count($unidadesInvalidas) === 1 ? 'unidad' : 'unidades')
            : (string) (reset($errores) ?: 'La cadena de tipos no es válida.'));
    }
}
