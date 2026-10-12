<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Estructura\Domain\Entities\Competencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\Exceptions\EstadoSinCambio;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\GradoLetra;
use App\Modules\Estructura\Domain\ValueObjects\PaginaCompetencias;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaEstadoHistorialModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentCompetenciaRepository implements CompetenciaRepositoryInterface
{
    public function listar(?string $q, ?TipoCompetencia $tipo, ?EstadoCompetencia $estado, int $pagina, int $porPagina): PaginaCompetencias
    {
        $consulta = CompetenciaModel::query();

        if ($q !== null && $q !== '') {
            $patron = '%'.addcslashes(mb_strtolower($q), '%_\\').'%';
            $consulta->whereRaw('nombre_clave like ? escape ?', [$patron, '\\']);
        }

        if ($tipo !== null) {
            $consulta->where('tipo', $tipo->value);
        }

        if ($estado !== null) {
            $consulta->where('estado', $estado->value);
        }

        $total = (clone $consulta)->count();
        $modelos = $consulta->orderBy('nombre')
            ->skip(($pagina - 1) * $porPagina)
            ->take($porPagina)
            ->get();

        return new PaginaCompetencias($modelos->map(fn (CompetenciaModel $m) => $this->aEntidad($m, false))->all(), $total);
    }

    public function obtener(int $id): ?Competencia
    {
        $modelo = CompetenciaModel::query()->with('grados')->find($id);

        return $modelo === null ? null : $this->aEntidad($modelo, true);
    }

    public function obtenerPorIds(array $ids): array
    {
        return CompetenciaModel::query()->with('grados')->whereIn('id', $ids)->orderBy('nombre')->get()
            ->map(fn (CompetenciaModel $m) => $this->aEntidad($m, true))->all();
    }

    public function obtenerActivas(): array
    {
        return CompetenciaModel::query()->with('grados')->where('estado', EstadoCompetencia::Activo->value)->orderBy('nombre')->get()
            ->map(fn (CompetenciaModel $m) => $this->aEntidad($m, true))->all();
    }

    public function crear(string $nombre, string $descripcion, TipoCompetencia $tipo, array $grados, ?int $actorId): Competencia
    {
        try {
            $id = DB::transaction(function () use ($nombre, $descripcion, $tipo, $grados, $actorId) {
                $modelo = CompetenciaModel::query()->create([
                    'nombre' => $nombre,
                    'nombre_clave' => CompetenciaModel::claveDeNombre($nombre),
                    'descripcion' => $descripcion,
                    'tipo' => $tipo->value,
                    'estado' => EstadoCompetencia::Activo->value,
                    'created_by' => $actorId,
                ]);
                // El código se deriva del id (autoincremental, sin reutilización) y no vuelve a cambiar.
                $modelo->forceFill(['codigo' => sprintf('COMP-%04d', $modelo->id)])->save();
                $this->guardarGrados($modelo->id, $grados);

                return $modelo->id;
            });
        } catch (UniqueConstraintViolationException) {
            throw new NombreDuplicado;
        }

        return $this->obtener($id) ?? throw new CompetenciaNoEncontrada;
    }

    public function actualizar(int $id, int $versionEsperada, string $nombre, string $descripcion, TipoCompetencia $tipo, array $grados, ?int $actorId): Competencia
    {
        try {
            DB::transaction(function () use ($id, $versionEsperada, $nombre, $descripcion, $tipo, $grados, $actorId) {
                $afectadas = CompetenciaModel::query()
                    ->where('id', $id)
                    ->where('version', $versionEsperada)
                    ->update([
                        'nombre' => $nombre,
                        'nombre_clave' => CompetenciaModel::claveDeNombre($nombre),
                        'descripcion' => $descripcion,
                        'tipo' => $tipo->value,
                        'version' => DB::raw('version + 1'),
                        'updated_by' => $actorId,
                        'updated_at' => now(),
                    ]);

                if ($afectadas === 0) {
                    throw CompetenciaModel::query()->whereKey($id)->exists()
                        ? new ConflictoDeVersion
                        : new CompetenciaNoEncontrada;
                }

                $this->guardarGrados($id, $grados);
            });
        } catch (UniqueConstraintViolationException) {
            throw new NombreDuplicado;
        }

        return $this->obtener($id) ?? throw new CompetenciaNoEncontrada;
    }

    public function cambiarEstado(int $id, EstadoCompetencia $nuevo, ?int $actorId): Competencia
    {
        DB::transaction(function () use ($id, $nuevo, $actorId) {
            // Actualización condicional sobre la tabla (sin tocar updated_at/updated_by ni version):
            // dos cambios simultáneos al mismo estado dejan un solo registro de historial.
            $afectadas = DB::table('competencias')
                ->where('id', $id)
                ->where('estado', '<>', $nuevo->value)
                ->update(['estado' => $nuevo->value]);

            if ($afectadas === 0) {
                throw CompetenciaModel::query()->whereKey($id)->exists()
                    ? new EstadoSinCambio
                    : new CompetenciaNoEncontrada;
            }

            CompetenciaEstadoHistorialModel::query()->create([
                'competencia_id' => $id,
                'estado_anterior' => $nuevo->opuesto()->value,
                'estado_nuevo' => $nuevo->value,
                'user_id' => $actorId,
            ]);
        });

        return $this->obtener($id) ?? throw new CompetenciaNoEncontrada;
    }

    /**
     * @param  array<string, string>  $grados
     */
    private function guardarGrados(int $competenciaId, array $grados): void
    {
        foreach (GradoLetra::letras() as $letra) {
            CompetenciaGradoModel::query()->updateOrCreate(
                ['competencia_id' => $competenciaId, 'grado' => $letra],
                ['descripcion' => $grados[$letra]],
            );
        }
    }

    private function aEntidad(CompetenciaModel $modelo, bool $conGrados): Competencia
    {
        $grados = [];
        if ($conGrados) {
            foreach ($modelo->grados as $grado) {
                $grados[$grado->grado] = $grado->descripcion;
            }
        }

        return new Competencia(
            id: $modelo->id,
            codigo: (string) $modelo->codigo,
            nombre: $modelo->nombre,
            descripcion: $modelo->descripcion,
            tipo: TipoCompetencia::from($modelo->tipo),
            estado: EstadoCompetencia::from($modelo->estado),
            version: (int) $modelo->version,
            creadoPor: $modelo->created_by === null ? null : (int) $modelo->created_by,
            modificadoPor: $modelo->updated_by === null ? null : (int) $modelo->updated_by,
            grados: $grados,
        );
    }
}
