<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

final class ColaboradorNoEvaluable extends RuntimeException
{
    public function __construct(public readonly string $motivo)
    {
        parent::__construct(match ($motivo) {
            'NoExiste' => 'El colaborador no existe.',
            'SinPuesto' => 'El colaborador no tiene un puesto asignado.',
            'SinPerfil' => 'El puesto del colaborador no tiene perfil de competencias.',
            'SinVinculoVigente' => 'El colaborador no tiene un vínculo laboral vigente.',
            default => 'El colaborador no se puede evaluar.',
        });
    }
}
