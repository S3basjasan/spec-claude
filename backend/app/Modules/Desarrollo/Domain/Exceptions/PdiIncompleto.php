<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** El plan no cumple los requisitos para confirmar el acuerdo (FR-026). */
final class PdiIncompleto extends ErrorDePdi
{
    /**
     * @param  list<string>  $faltantes  sin_compromisos, compromiso_sin_acciones, sin_responsable, sin_periodo, responsable_sin_permiso
     */
    public function __construct(public readonly array $faltantes)
    {
        parent::__construct(
            'El plan está incompleto: '.implode(', ', array_map(self::texto(...), $faltantes)).'.',
            'plan_incompleto',
            null,
            ['completitud' => $faltantes],
            ['completitud' => $faltantes],
        );
    }

    private static function texto(string $clave): string
    {
        return match ($clave) {
            'sin_compromisos' => 'agrega al menos un compromiso',
            'compromiso_sin_acciones' => 'cada compromiso necesita al menos una acción',
            'sin_responsable' => 'define el responsable',
            'sin_periodo' => 'define el periodo',
            'responsable_sin_permiso' => 'el responsable ya no es administrador',
            default => $clave,
        };
    }
}
