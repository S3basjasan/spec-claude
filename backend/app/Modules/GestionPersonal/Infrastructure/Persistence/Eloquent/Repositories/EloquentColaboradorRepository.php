<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\Colaborador;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\FichaModificada;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeFicha;
use App\Modules\GestionPersonal\Domain\ValueObjects\CodigoColaborador;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentColaboradorRepository implements ColaboradorRepositoryInterface
{
    public function porId(int $tenantId, int $id): ?Colaborador
    {
        return $this->aEntidad(
            ColaboradorModel::query()->where('tenant_id', $tenantId)->whereKey($id)->first(),
        );
    }

    public function porIdentificacion(int $tenantId, string $tipo, string $clave): ?Colaborador
    {
        return $this->aEntidad(
            ColaboradorModel::query()
                ->where('tenant_id', $tenantId)
                ->where('tipo_identificacion', $tipo)
                ->where('identificacion_clave', $clave)
                ->first(),
        );
    }

    public function porOperacion(int $tenantId, string $operacionId): ?array
    {
        $modelo = ColaboradorModel::query()
            ->where('tenant_id', $tenantId)
            ->where('operacion_id', $operacionId)
            ->first();

        if ($modelo === null) {
            return null;
        }

        return [
            'colaborador' => $this->aEntidad($modelo),
            'hash' => (string) $modelo->operacion_hash,
            'actor' => (int) $modelo->created_by,
        ];
    }

    public function crear(int $tenantId, int $actorId, string $operacionId, string $hash, array $datos, string $clave, string $nombresBusqueda, string $apellidosBusqueda): Colaborador
    {
        try {
            $modelo = DB::transaction(function () use ($tenantId, $actorId, $operacionId, $hash, $datos, $clave, $nombresBusqueda, $apellidosBusqueda) {
                $modelo = ColaboradorModel::query()->create(array_merge($datos, [
                    'tenant_id' => $tenantId,
                    // Código temporal único: el definitivo se deriva del id en esta misma transacción.
                    'codigo' => 'TMP-'.$operacionId,
                    'identificacion_clave' => $clave,
                    'nombres_busqueda' => $nombresBusqueda,
                    'apellidos_busqueda' => $apellidosBusqueda,
                    'version' => 1,
                    'operacion_id' => $operacionId,
                    'operacion_hash' => $hash,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]));

                $modelo->codigo = CodigoColaborador::desdeId((int) $modelo->id);
                $modelo->save();

                return $modelo;
            });
        } catch (UniqueConstraintViolationException) {
            throw new ConflictoDeUnicidad;
        }

        return $this->aEntidad($modelo);
    }

    public function actualizar(int $tenantId, int $id, int $version, int $actorId, array $cambios): Colaborador
    {
        try {
            $filas = ColaboradorModel::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->where('version', $version)
                ->update(array_merge($cambios, [
                    'version' => DB::raw('version + 1'),
                    'updated_by' => $actorId,
                    'updated_at' => now(),
                ]));
        } catch (UniqueConstraintViolationException) {
            throw new ConflictoDeUnicidad;
        }

        if ($filas === 0) {
            throw new FichaModificada;
        }

        return $this->porId($tenantId, $id) ?? throw new FichaModificada;
    }

    public function listar(int $tenantId, array $tokens, ?string $codigo, ?array $idsPorPuesto, ?array $incluirIds, ?array $excluirIds, int $pagina, int $porPagina): array
    {
        $consulta = ColaboradorModel::query()->where('tenant_id', $tenantId);

        if ($tokens !== [] || $codigo !== null || ($idsPorPuesto !== null && $idsPorPuesto !== [])) {
            $consulta->where(function ($busqueda) use ($tokens, $codigo, $idsPorPuesto) {
                if ($tokens !== []) {
                    $busqueda->orWhere(function ($nombres) use ($tokens) {
                        foreach ($tokens as $token) {
                            $nombres->whereRaw("(nombres_busqueda || ' ' || apellidos_busqueda) like ? escape '\\'", ['%'.$this->escaparLike($token).'%']);
                        }
                    });
                }
                if ($codigo !== null) {
                    $busqueda->orWhereRaw("lower(codigo) like ? escape '\\'", ['%'.$this->escaparLike($codigo).'%']);
                }
                if ($idsPorPuesto !== null && $idsPorPuesto !== []) {
                    $busqueda->orWhereIn('id', $idsPorPuesto);
                }
            });
        }

        if ($incluirIds !== null) {
            $consulta->whereIn('id', $incluirIds);
        }
        if ($excluirIds !== null && $excluirIds !== []) {
            $consulta->whereNotIn('id', $excluirIds);
        }

        $total = (clone $consulta)->count();

        $filas = $consulta
            ->orderBy('apellidos_busqueda')
            ->orderBy('nombres_busqueda')
            ->orderBy('id')
            ->forPage($pagina, $porPagina)
            ->get();

        return [
            'items' => $filas->map(fn (ColaboradorModel $m) => $this->aEntidad($m))->all(),
            'total' => $total,
        ];
    }

    public function hayFichas(int $tenantId): bool
    {
        return ColaboradorModel::query()->where('tenant_id', $tenantId)->exists();
    }

    private function escaparLike(string $texto): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto);
    }

    private function aEntidad(?ColaboradorModel $modelo): ?Colaborador
    {
        if ($modelo === null) {
            return null;
        }

        $datos = [];
        foreach ([...ReglasDeFicha::OBLIGATORIOS, ...ReglasDeFicha::OPCIONALES] as $campo) {
            $valor = $modelo->getAttribute($campo);
            $datos[$campo] = $campo === 'numero_hijos' && $valor !== null ? (int) $valor : $valor;
        }

        return new Colaborador(
            (int) $modelo->id,
            (int) $modelo->tenant_id,
            (string) $modelo->codigo,
            (int) $modelo->version,
            $datos,
            (int) $modelo->created_by,
            (int) $modelo->updated_by,
            $modelo->created_at->toIso8601ZuluString(),
            $modelo->updated_at->toIso8601ZuluString(),
        );
    }
}
