<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Estructura\Domain\Entities\RegistroComportamientos;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaYaTieneRegistro;
use App\Modules\Estructura\Domain\Exceptions\RegistroModificado;
use App\Modules\Estructura\Domain\Exceptions\RegistroNoEncontrado;
use App\Modules\Estructura\Domain\Repositories\ComportamientoRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaAsociada;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\GradoLetra;
use App\Modules\Estructura\Domain\ValueObjects\PaginaRegistrosComportamientos;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\ComportamientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\RegistroComportamientosModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentComportamientoRepository implements ComportamientoRepositoryInterface
{
    // ---------------------------------------------------------------- Lecturas

    public function listar(?string $q, ?TipoCompetencia $tipo, ?EstadoCompetencia $estado, int $pagina, int $porPagina): PaginaRegistrosComportamientos
    {
        $consulta = CompetenciaModel::query()->whereHas('registroComportamientos');

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
        $competencias = $consulta->with('registroComportamientos')
            ->orderBy('nombre')
            ->skip(($pagina - 1) * $porPagina)
            ->take($porPagina)
            ->get();

        return new PaginaRegistrosComportamientos(
            $competencias->map(fn (CompetenciaModel $c) => $this->aEntidad($c, false))->all(),
            $total,
        );
    }

    public function obtener(int $competenciaId): ?RegistroComportamientos
    {
        $competencia = $this->conRegistro()->find($competenciaId);

        return $competencia === null ? null : $this->aEntidad($competencia, true);
    }

    public function obtenerPorCompetencias(array $competenciaIds): array
    {
        return $this->conRegistro()->whereIn('id', $competenciaIds)->orderBy('nombre')->get()
            ->map(fn (CompetenciaModel $c) => $this->aEntidad($c, true))->all();
    }

    public function obtenerActivos(): array
    {
        return $this->conRegistro()->where('estado', EstadoCompetencia::Activo->value)->orderBy('nombre')->get()
            ->map(fn (CompetenciaModel $c) => $this->aEntidad($c, true))->all();
    }

    public function disponibles(): array
    {
        return CompetenciaModel::query()
            ->where('estado', EstadoCompetencia::Activo->value)
            ->whereDoesntHave('registroComportamientos')
            ->orderBy('nombre')
            ->get()
            ->map(fn (CompetenciaModel $c) => $this->asociada($c))
            ->all();
    }

    // ------------------------------------------------------------- Escrituras

    public function crear(int $competenciaId, array $comportamientos, ?int $actorId): RegistroComportamientos
    {
        DB::transaction(function () use ($competenciaId, $comportamientos, $actorId) {
            $competencia = CompetenciaModel::query()->find($competenciaId);

            if ($competencia === null || $competencia->estado !== EstadoCompetencia::Activo->value) {
                throw new CompetenciaNoDisponible;
            }

            if (RegistroComportamientosModel::query()->where('competencia_id', $competenciaId)->exists()) {
                throw new CompetenciaYaTieneRegistro;
            }

            try {
                RegistroComportamientosModel::query()->create([
                    'competencia_id' => $competenciaId,
                    'version' => 1,
                    'created_by' => $actorId,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Otra persona creó el registro entre la comprobación y la inserción.
                throw new CompetenciaYaTieneRegistro;
            }

            foreach (GradoLetra::letras() as $letra) {
                $grado = $this->grado($competenciaId, $letra);
                foreach (array_values($comportamientos[$letra]) as $indice => $texto) {
                    ComportamientoModel::query()->create([
                        'competencia_grado_id' => $grado->id,
                        'orden' => $indice + 1,
                        'descripcion' => $texto,
                        'created_by' => $actorId,
                    ]);
                }
            }
        });

        return $this->obtener($competenciaId) ?? throw new RegistroNoEncontrado;
    }

    public function actualizar(int $competenciaId, int $versionEsperada, array $comportamientos, ?int $actorId): RegistroComportamientos
    {
        DB::transaction(function () use ($competenciaId, $versionEsperada, $comportamientos, $actorId) {
            $existe = RegistroComportamientosModel::query()->where('competencia_id', $competenciaId)->exists();
            if (! $existe) {
                throw new RegistroNoEncontrado;
            }

            // Guardar sin cambios reales no modifica nada: ni filas, ni versión, ni auditoría.
            if ($this->textosActuales($competenciaId) === $this->normalizar($comportamientos)) {
                return;
            }

            $afectadas = RegistroComportamientosModel::query()
                ->where('competencia_id', $competenciaId)
                ->where('version', $versionEsperada)
                ->update([
                    'version' => DB::raw('version + 1'),
                    'updated_by' => $actorId,
                    'updated_at' => now(),
                ]);

            if ($afectadas === 0) {
                throw new RegistroModificado;
            }

            foreach (GradoLetra::letras() as $letra) {
                $this->reemplazarGrado($this->grado($competenciaId, $letra), array_values($comportamientos[$letra]), $actorId);
            }
        });

        return $this->obtener($competenciaId) ?? throw new RegistroNoEncontrado;
    }

    // ------------------------------------------------------------------ Apoyo

    /** @return Builder<CompetenciaModel> */
    private function conRegistro(): Builder
    {
        return CompetenciaModel::query()
            ->whereHas('registroComportamientos')
            ->with(['registroComportamientos', 'grados.comportamientos']);
    }

    private function grado(int $competenciaId, string $letra): CompetenciaGradoModel
    {
        return CompetenciaGradoModel::query()
            ->where('competencia_id', $competenciaId)
            ->where('grado', $letra)
            ->firstOrFail();
    }

    /**
     * Actualiza por `orden` las filas existentes (conserva `created_by`), inserta las nuevas y elimina las sobrantes.
     *
     * @param  array<int, string>  $textos
     */
    private function reemplazarGrado(CompetenciaGradoModel $grado, array $textos, ?int $actorId): void
    {
        $existentes = ComportamientoModel::query()->where('competencia_grado_id', $grado->id)->get()->keyBy('orden');

        foreach ($textos as $indice => $texto) {
            $orden = $indice + 1;
            $fila = $existentes->get($orden);

            if ($fila === null) {
                ComportamientoModel::query()->create([
                    'competencia_grado_id' => $grado->id,
                    'orden' => $orden,
                    'descripcion' => $texto,
                    'created_by' => $actorId,
                ]);
            } elseif ($fila->descripcion !== $texto) {
                $fila->update(['descripcion' => $texto, 'updated_by' => $actorId]);
            }
        }

        ComportamientoModel::query()
            ->where('competencia_grado_id', $grado->id)
            ->where('orden', '>', count($textos))
            ->delete();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function textosActuales(int $competenciaId): array
    {
        $actuales = [];
        foreach (GradoLetra::letras() as $letra) {
            $actuales[$letra] = ComportamientoModel::query()
                ->where('competencia_grado_id', $this->grado($competenciaId, $letra)->id)
                ->orderBy('orden')
                ->pluck('descripcion')
                ->all();
        }

        return $actuales;
    }

    /**
     * @param  array<string, array<int, string>>  $comportamientos
     * @return array<string, array<int, string>>
     */
    private function normalizar(array $comportamientos): array
    {
        $normalizados = [];
        foreach (GradoLetra::letras() as $letra) {
            $normalizados[$letra] = array_map(fn (string $texto) => trim($texto), array_values($comportamientos[$letra]));
        }

        return $normalizados;
    }

    private function asociada(CompetenciaModel $competencia): CompetenciaAsociada
    {
        return new CompetenciaAsociada(
            $competencia->id,
            (string) $competencia->codigo,
            $competencia->nombre,
            $competencia->descripcion,
            TipoCompetencia::from($competencia->tipo),
            EstadoCompetencia::from($competencia->estado),
        );
    }

    private function aEntidad(CompetenciaModel $competencia, bool $conComportamientos): RegistroComportamientos
    {
        $registro = $competencia->registroComportamientos;

        $porGrado = [];
        if ($conComportamientos) {
            foreach (GradoLetra::letras() as $letra) {
                $porGrado[$letra] = [];
            }
            foreach ($competencia->grados as $grado) {
                $porGrado[$grado->grado] = $grado->comportamientos->pluck('descripcion')->all();
            }
        }

        return new RegistroComportamientos(
            competencia: $this->asociada($competencia),
            version: (int) $registro->version,
            creadoPor: $registro->created_by === null ? null : (int) $registro->created_by,
            modificadoPor: $registro->updated_by === null ? null : (int) $registro->updated_by,
            comportamientos: $porGrado,
        );
    }
}
