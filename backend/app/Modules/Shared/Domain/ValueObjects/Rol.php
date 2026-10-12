<?php

namespace App\Modules\Shared\Domain\ValueObjects;

enum Rol: string
{
    case Admin = 'admin';
    case User = 'user';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::User => 'Usuario',
        };
    }
}
