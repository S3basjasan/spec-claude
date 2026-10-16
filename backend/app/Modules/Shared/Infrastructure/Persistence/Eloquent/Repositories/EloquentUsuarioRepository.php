<?php

namespace App\Modules\Shared\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Shared\Domain\Entities\Usuario;
use App\Modules\Shared\Domain\Repositories\UsuarioRepositoryInterface;
use App\Modules\Shared\Domain\ValueObjects\Rol;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;

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
