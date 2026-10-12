<?php

namespace App\Modules\Shared\Infrastructure\Security;

use App\Modules\Shared\Application\Ports\HasherInterface;
use Illuminate\Support\Facades\Hash;

final class LaravelHasher implements HasherInterface
{
    private static ?string $ficticio = null;

    public function verificar(string $plano, string $hash): bool
    {
        return Hash::check($plano, $hash);
    }

    public function hashFicticio(): string
    {
        return self::$ficticio ??= Hash::make('contrasena-ficticia');
    }
}
