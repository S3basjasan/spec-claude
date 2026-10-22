<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

final class ComparacionInvalida extends ErrorDeAdecuacion
{
    public static function candidatos(): self
    {
        return new self('Elige entre 2 y 20 candidatos, sin repetir.', 'candidatos_invalidos', 'colaborador_ids');
    }

    public static function puesto(): self
    {
        return new self('El puesto a cubrir no existe.', 'objetivo_no_definido', 'puesto_objetivo_id');
    }
}
