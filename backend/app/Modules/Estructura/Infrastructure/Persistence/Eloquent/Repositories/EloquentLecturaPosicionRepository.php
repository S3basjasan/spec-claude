<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Estructura\Domain\Repositories\LecturaPosicionRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentLecturaPosicionRepository implements LecturaPosicionRepositoryInterface
{
    public function obtener(int $id): ?array
    {
        return $this->porIds([$id])[$id] ?? null;
    }

    public function porIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $filas = $this->base()->whereIn('posiciones.id', array_values(array_unique($ids)))->get();

        $salida = [];
        foreach ($filas as $fila) {
            $salida[(int) $fila->id] = $this->aArreglo($fila);
        }

        return $salida;
    }

    public function asignables(int $tenantId, ?string $texto, int $pagina, int $porPagina): array
    {
        $consulta = $this->base()
            ->where('posiciones.tenant_id', $tenantId)
            ->where('posiciones.estado', '<>', 'Suprimida')
            ->where('puestos.estado', 'Activo')
            ->whereNotNull('posiciones.unidad_organizacional_id')
            ->where('unidades_organizacionales.estado', 'Activa');

        $busqueda = $texto === null ? '' : mb_strtolower(trim(Str::ascii($texto)));
        if ($busqueda !== '') {
            $unidadesQueCoinciden = DB::table('unidades_organizacionales')->get(['id', 'nombre'])
                ->filter(fn ($unidad) => str_contains(mb_strtolower(Str::ascii((string) $unidad->nombre)), $busqueda))
                ->pluck('id')->all();
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busqueda).'%';

            $consulta->where(function (Builder $q) use ($like, $unidadesQueCoinciden) {
                $q->whereRaw("puestos.nombre_busqueda like ? escape '\\'", [$like]);
                if ($unidadesQueCoinciden !== []) {
                    $q->orWhereIn('posiciones.unidad_organizacional_id', $unidadesQueCoinciden);
                }
            });
        }

        $total = (clone $consulta)->count();
        $filas = $consulta->orderBy('puestos.nombre')->orderBy('posiciones.id')->forPage($pagina, $porPagina)->get();

        return [
            'items' => $filas->map(fn ($fila) => [
                'id' => (int) $fila->id,
                'puesto' => (string) $fila->puesto_nombre,
                'unidad' => (string) $fila->unidad_nombre,
                'jefatura' => null,
            ])->all(),
            'total' => $total,
        ];
    }

    public function motivoNoAsignable(int $id, int $tenantId): ?string
    {
        $posicion = $this->obtener($id);

        if ($posicion === null) {
            return 'inexistente';
        }
        if ($posicion['tenant_id'] !== $tenantId) {
            return 'otro_tenant';
        }
        if ($posicion['estado'] === 'Suprimida') {
            return 'suprimida';
        }
        if (! $posicion['puesto_activo']) {
            return 'puesto_inactivo';
        }
        if ($posicion['unidad_id'] === null || ! $posicion['unidad_activa']) {
            return 'unidad_inactiva';
        }

        return null;
    }

    public function idsPorPuesto(string $texto): array
    {
        $busqueda = mb_strtolower(trim(Str::ascii($texto)));
        if ($busqueda === '') {
            return [];
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busqueda).'%';

        return DB::table('posiciones')
            ->join('puestos', 'puestos.id', '=', 'posiciones.puesto_id')
            ->whereRaw("puestos.nombre_busqueda like ? escape '\\'", [$like])
            ->pluck('posiciones.id')->map(fn ($id) => (int) $id)->all();
    }

    public function idsPorUnidad(int $unidadId): array
    {
        return DB::table('posiciones')->where('unidad_organizacional_id', $unidadId)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function contarEnUsoDelPuesto(int $puestoId): int
    {
        return DB::table('posiciones')->where('puesto_id', $puestoId)->where('estado', '<>', 'Suprimida')->count();
    }

    public function suprimidasDelPuesto(int $puestoId): array
    {
        return DB::table('posiciones')->where('puesto_id', $puestoId)->where('estado', 'Suprimida')
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function suprimidasDeUnidades(array $unidadIds): array
    {
        if ($unidadIds === []) {
            return [];
        }

        $salida = [];
        $filas = DB::table('posiciones')->whereIn('unidad_organizacional_id', $unidadIds)->where('estado', 'Suprimida')
            ->get(['id', 'unidad_organizacional_id']);
        foreach ($filas as $fila) {
            $salida[(int) $fila->unidad_organizacional_id][] = (int) $fila->id;
        }

        return $salida;
    }

    private function base(): Builder
    {
        return DB::table('posiciones')
            ->join('puestos', 'puestos.id', '=', 'posiciones.puesto_id')
            ->leftJoin('unidades_organizacionales', 'unidades_organizacionales.id', '=', 'posiciones.unidad_organizacional_id')
            ->select([
                'posiciones.id', 'posiciones.tenant_id', 'posiciones.estado', 'posiciones.puesto_id',
                'puestos.nombre as puesto_nombre', 'puestos.estado as puesto_estado',
                'posiciones.unidad_organizacional_id as unidad_id',
                'unidades_organizacionales.nombre as unidad_nombre', 'unidades_organizacionales.estado as unidad_estado',
            ]);
    }

    /**
     * @return array{id: int, tenant_id: int, estado: string, puesto_id: int, puesto: string, puesto_activo: bool, unidad_id: int|null, unidad: string|null, unidad_activa: bool, jefatura: null}
     */
    private function aArreglo(object $fila): array
    {
        return [
            'id' => (int) $fila->id,
            'tenant_id' => (int) $fila->tenant_id,
            'estado' => (string) $fila->estado,
            'puesto_id' => (int) $fila->puesto_id,
            'puesto' => (string) $fila->puesto_nombre,
            'puesto_activo' => $fila->puesto_estado === 'Activo',
            'unidad_id' => $fila->unidad_id === null ? null : (int) $fila->unidad_id,
            'unidad' => $fila->unidad_nombre === null ? null : (string) $fila->unidad_nombre,
            'unidad_activa' => $fila->unidad_estado === 'Activa',
            'jefatura' => null,
        ];
    }
}
