<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

use App\Modules\GestionPersonal\Domain\Entities\VinculoLaboral;

/** Situación derivada de los vínculos de una ficha para una fecha; nunca se almacena. */
final class SituacionLaboral
{
    public const ACTIVO = 'Activo';

    public const DESVINCULADO = 'Desvinculado';

    public const SIN_VINCULO = 'SinVinculoVigente';

    public function __construct(
        public readonly string $estado,
        public readonly ?VinculoLaboral $vigente,
        public readonly ?VinculoLaboral $proximo,
    ) {}
}
