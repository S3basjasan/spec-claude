<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class ColaboradorInvalido extends ErrorDePlan
{
    public function __construct(?string $campo = 'colaborador_ids')
    {
        parent::__construct('Algún colaborador no existe o no tiene vínculo laboral vigente.', 'colaborador_invalido', $campo);
    }
}
