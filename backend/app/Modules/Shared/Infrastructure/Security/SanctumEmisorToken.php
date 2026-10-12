<?php

namespace App\Modules\Shared\Infrastructure\Security;

use App\Modules\Shared\Application\Ports\EmisorTokenInterface;
use App\Modules\Shared\Domain\Entities\Usuario;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\Request;

final class SanctumEmisorToken implements EmisorTokenInterface
{
    public function __construct(private readonly Request $request) {}

    public function emitir(Usuario $usuario): string
    {
        $capacidad = $usuario->esAdministrador() ? ['admin'] : ['user'];

        return UserModel::query()->findOrFail($usuario->id)
            ->createToken('auth', $capacidad)
            ->plainTextToken;
    }

    public function revocarActual(int $usuarioId): void
    {
        $actual = $this->request->user();

        if ($actual instanceof UserModel && $actual->getKey() === $usuarioId) {
            $actual->currentAccessToken()?->delete();
        }
    }
}
