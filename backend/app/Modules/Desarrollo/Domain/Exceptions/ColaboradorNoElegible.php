<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class ColaboradorNoElegible extends ErrorDeAdecuacion
{
    public function __construct()
    {
        parent::__construct('El colaborador no existe o no tiene un vínculo laboral vigente.', 'colaborador_no_elegible', 'colaborador_id');
    }
}
