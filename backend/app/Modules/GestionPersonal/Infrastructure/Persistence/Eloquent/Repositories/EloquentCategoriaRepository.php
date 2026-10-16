<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\CategoriaDocumental;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaInactiva;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaModificada;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Exceptions\NombreDeCategoriaDuplicado;
use App\Modules\GestionPersonal\Domain\Repositories\CategoriaRepositoryInterface;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\CategoriaDocumentalModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentCategoriaRepository implements CategoriaRepositoryInterface
{
    public function porId(int $tenantId, int $id): ?CategoriaDocumental
    {
        $modelo = CategoriaDocumentalModel::query()->where('tenant_id', $tenantId)->whereKey($id)->first();

        return $modelo === null ? null : $this->aEntidad($modelo);
    }

    public function listar(int $tenantId, ?string $q, string $estado, int $pagina, int $porPagina): array
    {
        $consulta = CategoriaDocumentalModel::query()->where('tenant_id', $tenantId);

        if ($estado !== 'Todas') {
            $consulta->where('estado', $estado);
        }

        if ($q !== null && $q !== '') {
            $consulta->whereRaw("nombre_busqueda like ? escape '\\'", ['%'.$this->escaparLike($q).'%']);
        }

        $total = (clone $consulta)->count();

        $items = $consulta
            ->orderBy('nombre_busqueda')
            ->orderBy('id')
            ->forPage($pagina, $porPagina)
            ->get()
            ->map(fn (CategoriaDocumentalModel $m) => $this->aEntidad($m))
            ->all();

        return ['items' => $items, 'total' => $total];
    }

    public function existePorClave(int $tenantId, string $nombreClave, ?int $excluirId = null): bool
    {
        $consulta = CategoriaDocumentalModel::query()->where('tenant_id', $tenantId)->where('nombre_clave', $nombreClave);

        if ($excluirId !== null) {
            $consulta->whereKeyNot($excluirId);
        }

        return $consulta->exists();
    }

    public function crear(int $tenantId, int $actorId, string $nombre, string $nombreClave, string $nombreBusqueda): CategoriaDocumental
    {
        try {
            $modelo = CategoriaDocumentalModel::query()->create([
                'tenant_id' => $tenantId,
                'nombre' => $nombre,
                'nombre_clave' => $nombreClave,
                'nombre_busqueda' => $nombreBusqueda,
                'estado' => 'Activa',
                'version' => 1,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new NombreDeCategoriaDuplicado;
        }

        return $this->aEntidad($modelo->refresh());
    }

    public function actualizar(int $tenantId, int $id, int $version, int $actorId, array $cambios): CategoriaDocumental
    {
        try {
            $filas = CategoriaDocumentalModel::query()
                ->where('tenant_id', $tenantId)->whereKey($id)->where('version', $version)
                ->update(array_merge($cambios, [
                    'version' => DB::raw('version + 1'),
                    'updated_by' => $actorId,
                    'updated_at' => now(),
                ]));
        } catch (UniqueConstraintViolationException) {
            throw new NombreDeCategoriaDuplicado;
        }

        if ($filas === 0) {
            throw $this->porId($tenantId, $id) === null ? new CategoriaNoEncontrada : new CategoriaModificada;
        }

        return $this->porId($tenantId, $id) ?? throw new CategoriaNoEncontrada;
    }

    public function confirmarActiva(int $tenantId, int $id): void
    {
        // Query builder directo y sin tocar `updated_at`: solo toma el bloqueo de la fila hasta el commit del llamador.
        $filas = DB::table('categorias_documentales')
            ->where('tenant_id', $tenantId)->where('id', $id)->where('estado', 'Activa')
            ->update(['version' => DB::raw('version')]);

        if ($filas === 0) {
            throw new CategoriaInactiva;
        }
    }

    public function activas(int $tenantId): array
    {
        return CategoriaDocumentalModel::query()
            ->where('tenant_id', $tenantId)->where('estado', 'Activa')
            ->orderBy('nombre_busqueda')->orderBy('id')
            ->get()
            ->map(fn (CategoriaDocumentalModel $m) => $this->aEntidad($m))
            ->all();
    }

    public function porIds(int $tenantId, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $salida = [];
        $filas = CategoriaDocumentalModel::query()->where('tenant_id', $tenantId)->whereIn('id', $ids)->get();
        foreach ($filas as $fila) {
            $salida[(int) $fila->id] = $this->aEntidad($fila);
        }

        return $salida;
    }

    private function escaparLike(string $texto): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto);
    }

    private function aEntidad(CategoriaDocumentalModel $m): CategoriaDocumental
    {
        return new CategoriaDocumental(
            (int) $m->id,
            (int) $m->tenant_id,
            (string) $m->nombre,
            (string) $m->estado,
            (int) $m->version,
            (int) $m->created_by,
            (int) $m->updated_by,
            $m->created_at->toIso8601ZuluString(),
            $m->updated_at->toIso8601ZuluString(),
        );
    }
}
