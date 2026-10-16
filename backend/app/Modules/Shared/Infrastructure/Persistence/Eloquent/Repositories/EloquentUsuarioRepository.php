<?php

namespace App\Modules\Shared\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Shared\Domain\Entities\Usuario;
use App\Modules\Shared\Domain\Repositories\UsuarioRepositoryInterface;
use App\Modules\Shared\Domain\ValueObjects\Rol;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;

final class EloquentUsuarioRepository implements UsuarioRepositoryInterface
{
    public function buscarPorCorreo(string $correo): ?Usuario
    {
        return $this->aEntidad(UserModel::query()->where('email', $correo)->first());
    }

    public function obtenerPorId(int $id): ?Usuario
    {
        return $this->aEntidad(UserModel::query()->find($id));
    }

    public function nombresPorIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<int, string> $nombres */
        $nombres = UserModel::query()->whereIn('id', array_values(array_unique($ids)))->pluck('name', 'id')->all();

        return $nombres;
    }

    public function idsPorNombre(string $texto, int $limite): array
    {
        $buscado = mb_strtolower(trim(Str::ascii($texto)));
        if ($buscado === '') {
            return [];
        }

        return UserModel::query()->orderBy('name')->get(['id', 'name'])
            ->filter(fn (UserModel $usuario) => str_contains(mb_strtolower(Str::ascii((string) $usuario->name)), $buscado))
            ->take($limite)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function aEntidad(?UserModel $modelo): ?Usuario
    {
        if ($modelo === null) {
            return null;
        }

        return new Usuario(
            id: $modelo->id,
            nombre: $modelo->name,
            correo: $modelo->email,
            rol: Rol::tryFrom((string) $modelo->role) ?? Rol::User,
            passwordHash: $modelo->password,
            tenantId: (int) $modelo->tenant_id,
            tenantNombre: (string) TenantModel::query()->whereKey($modelo->tenant_id)->value('nombre'),
        );
    }
}
