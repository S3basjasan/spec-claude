<?php

namespace App\Modules\Shared\Application\Services;

use App\Modules\Shared\Application\UseCases\ObtenerNombresDeUsuarios;
use App\Modules\Shared\Domain\Repositories\UsuarioRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Servicio inter-módulos del Módulo Shared.
 *
 * C4 Nivel 3 — clase real detrás de [Component: Laravel Facade].
 * Expande con métodos get* que delegan a Application\UseCases.
 */
class SharedService
{
    public function __construct(
        private readonly ObtenerNombresDeUsuarios $nombresDeUsuarios,
        private readonly UsuarioRepositoryInterface $usuarios,
    ) {}

    /**
     * Resumen mínimo para verificar el cableado Facade -> Servicio.
     *
     * @return array<string, mixed>
     */
    public function getResumen(): array
    {
        return ['modulo' => 'Shared'];
    }

    /**
     * Nombres de los usuarios existentes entre los ids dados (para auditoría de otros módulos).
     *
     * @param  array<int, int>  $ids
     * @return array<int, string> id => nombre
     */
    public function getNombresDeUsuarios(array $ids): array
    {
        return $this->nombresDeUsuarios->ejecutar($ids);
    }

    /**
     * Usuarios con rol Administrador del tenant, por nombre (solo lectura; p. ej. aprobadores de planes).
     *
     * @return list<array{id: int, nombre: string}>
     */
    public function getAdministradores(int $tenantId): array
    {
        return DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('role', 'admin')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => (int) $u->id, 'nombre' => (string) $u->name])
            ->all();
    }

    /**
     * Contexto de tenant de una cuenta, o null si la cuenta no existe.
     *
     * @return array{tenant_id: int, nombre: string, zona_horaria: string}|null
     */
    public function getContextoDeTenant(int $userId): ?array
    {
        $fila = DB::table('users')
            ->join('tenants', 'tenants.id', '=', 'users.tenant_id')
            ->where('users.id', $userId)
            ->first(['tenants.id', 'tenants.nombre', 'tenants.zona_horaria']);

        return $fila === null ? null : [
            'tenant_id' => (int) $fila->id,
            'nombre' => (string) $fila->nombre,
            'zona_horaria' => (string) $fila->zona_horaria,
        ];
    }

    /**
     * Mismo contexto que `getContextoDeTenant`, resuelto por tenant (para procesos programados sin cuenta).
     *
     * @return array{tenant_id: int, nombre: string, zona_horaria: string}|null
     */
    public function getContextoDeTenantPorId(int $tenantId): ?array
    {
        $fila = DB::table('tenants')->where('id', $tenantId)->first(['id', 'nombre', 'zona_horaria']);

        return $fila === null ? null : [
            'tenant_id' => (int) $fila->id,
            'nombre' => (string) $fila->nombre,
            'zona_horaria' => (string) $fila->zona_horaria,
        ];
    }

    /** Máximo de usuarios que devuelve una búsqueda por nombre. */
    public const LIMITE_BUSQUEDA_POR_NOMBRE = 200;

    /**
     * Ids de los usuarios cuyo nombre contiene el texto (sin distinguir mayúsculas ni tildes), ordenados por nombre y
     * limitados a 200, para que otros módulos busquen por persona sin leer la tabla de usuarios.
     *
     * @return array<int, int>
     */
    public function buscarIdsDeUsuariosPorNombre(string $texto): array
    {
        return $this->usuarios->idsPorNombre($texto, self::LIMITE_BUSQUEDA_POR_NOMBRE);
    }
}
