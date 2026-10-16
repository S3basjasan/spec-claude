<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

/**
 * La posición incumple una regla que depende de datos (puesto, unidad, supervisor, estructura). Lleva un mensaje por campo.
 */
final class PosicionInvalida extends RuntimeException
{
    /**
     * @param  array<string, string>  $errores  Campo → mensaje.
     */
    public function __construct(public readonly array $errores)
    {
        parent::__construct((string) (reset($errores) ?: 'La posición no es válida.'));
    }

    public static function puestoObligatorio(): self
    {
        return new self(['puesto_id' => 'Selecciona el puesto.']);
    }

    public static function puestoInexistente(): self
    {
        return new self(['puesto_id' => 'El puesto no existe.']);
    }

    public static function puestoInactivo(): self
    {
        return new self(['puesto_id' => 'El puesto debe estar activo.']);
    }

    public static function unidadInexistente(): self
    {
        return new self(['unidad_organizacional_id' => 'La unidad organizacional no existe.']);
    }

    public static function unidadInactiva(): self
    {
        return new self(['unidad_organizacional_id' => 'La unidad organizacional debe estar activa.']);
    }

    public static function supervisorInexistente(): self
    {
        return new self(['posicion_jefe_id' => 'El supervisor no existe.']);
    }

    public static function supervisorSuprimido(): self
    {
        return new self(['posicion_jefe_id' => 'El supervisor no puede ser una posición suprimida.']);
    }

    public static function supervisorEsLaMisma(): self
    {
        return new self(['posicion_jefe_id' => 'Una posición no puede ser su propio supervisor.']);
    }

    public static function supervisorEsSubordinada(): self
    {
        return new self(['posicion_jefe_id' => 'El supervisor no puede ser una de sus subordinadas.']);
    }

    public static function raizOcupada(): self
    {
        return new self(['unidad_organizacional_id' => 'La Gerencia General ya tiene su posición (máxima autoridad).']);
    }

    /** Dos máximas autoridades a la vez: es la misma regla que la unidad raíz ocupada. */
    public static function maximaAutoridadYaExiste(): self
    {
        return self::raizOcupada();
    }

    public static function raizSoloMaximaAutoridad(): self
    {
        return new self(['unidad_organizacional_id' => 'La unidad raíz solo admite la máxima autoridad.']);
    }

    public static function supervisorObligatorio(): self
    {
        return new self(['posicion_jefe_id' => 'Selecciona el supervisor.']);
    }

    public static function supervisorEnRaiz(): self
    {
        return new self(['posicion_jefe_id' => 'La máxima autoridad no tiene supervisor.']);
    }

    public static function supervisorFueraDeJerarquia(): self
    {
        return new self(['posicion_jefe_id' => 'El supervisor debe pertenecer a la misma unidad o a una unidad superior.']);
    }

    public static function unidadPorDebajoDeSubordinadas(int $cantidad): self
    {
        return new self(['unidad_organizacional_id' => $cantidad === 1
            ? 'La unidad no puede estar por debajo de la de 1 subordinada vigente.'
            : "La unidad no puede estar por debajo de la de $cantidad subordinadas vigentes."]);
    }

    public static function maximaAutoridadSoloModalidad(): self
    {
        return new self(['unidad_organizacional_id' => 'En la máxima autoridad solo se puede cambiar la modalidad.']);
    }

    public static function suprimida(): self
    {
        return new self(['estado' => 'La posición está suprimida y no se puede editar.']);
    }
}
