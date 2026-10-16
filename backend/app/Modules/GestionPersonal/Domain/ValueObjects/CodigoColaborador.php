<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

final class CodigoColaborador
{
    /** EMP- seguido del id con al menos 3 dígitos; crece sin tope (EMP-1000). */
    public static function desdeId(int $id): string
    {
        return 'EMP-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }
}
