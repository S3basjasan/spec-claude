<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Estructura\Domain\Entities\RegistroPreguntas;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaYaTienePreguntas;
use App\Modules\Estructura\Domain\Exceptions\PreguntasNoEncontradas;
use App\Modules\Estructura\Domain\Exceptions\RegistroModificado;
use App\Modules\Estructura\Domain\Repositories\PreguntaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaAsociada;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\PaginaRegistrosPreguntas;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PreguntaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\RegistroPreguntasModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentPreguntaRepository implements PreguntaRepositoryInterface
{
    // ---------------------------------------------------------------- Lecturas

    public function listar(?string $q, ?TipoCompetencia $tipo, ?EstadoCompetencia $estado, int $pagina, int $porPagina): PaginaRegistrosPreguntas
    {
        $consulta = CompetenciaModel::query()->whereHas('registroPreguntas');

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
        $competencias = $consulta->with('registroPreguntas')
            ->orderBy('nombre')
            ->skip(($pagina - 1) * $porPagina)
            ->take($porPagina)
            ->get();

        return new PaginaRegistrosPreguntas(
            $competencias->map(fn (CompetenciaModel $c) => $this->aEntidad($c, false))->all(),
            $total,
        );
    }

    public function obtener(int $competenciaId): ?RegistroPreguntas
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
            ->whereDoesntHave('registroPreguntas')
            ->orderBy('nombre')
            ->get()
            ->map(fn (CompetenciaModel $c) => $this->asociada($c))
            ->all();
    }

    // ------------------------------------------------------------- Escrituras

    public function crear(int $competenciaId, array $preguntas, ?int $actorId): RegistroPreguntas
    {
        DB::transaction(function () use ($competenciaId, $preguntas, $actorId) {
            $competencia = CompetenciaModel::query()->find($competenciaId);

            if ($competencia === null || $competencia->estado !== EstadoCompetencia::Activo->value) {
                throw new CompetenciaNoDisponible;
            }

            if (RegistroPreguntasModel::query()->where('competencia_id', $competenciaId)->exists()) {
                throw new CompetenciaYaTienePreguntas;
            }

            try {
                $registro = RegistroPreguntasModel::query()->create([
                    'competencia_id' => $competenciaId,
                    'version' => 1,
                    'created_by' => $actorId,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Otra persona creó el registro entre la comprobación y la inserción.
                throw new CompetenciaYaTienePreguntas;
            }

            foreach (array_values($preguntas) as $indice => $texto) {
                PreguntaModel::query()->create([
                    'registro_id' => $registro->id,
                    'orden' => $indice + 1,
                    'pregunta' => $texto,
                    'created_by' => $actorId,
                ]);
            }
        });

        return $this->obtener($competenciaId) ?? throw new PreguntasNoEncontradas;
    }

    public function actualizar(int $competenciaId, int $versionEsperada, array $preguntas, ?int $actorId): RegistroPreguntas
    {
        DB::transaction(function () use ($competenciaId, $versionEsperada, $preguntas, $actorId) {
            $registro = RegistroPreguntasModel::query()->where('competencia_id', $competenciaId)->first();
            if ($registro === null) {
                throw new PreguntasNoEncontradas;
            }

            $nuevas = array_map(fn (string $texto) => trim($texto), array_values($preguntas));
            $actuales = PreguntaModel::query()->where('registro_id', $registro->id)->orderBy('orden')->get()->keyBy('orden');

            // Guardar sin cambios reales no modifica nada: ni filas, ni versión, ni auditoría.
            if ($actuales->pluck('pregunta')->values()->all() === $nuevas) {
                return;
            }

            $afectadas = RegistroPreguntasModel::query()
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

            // Siempre son cuatro filas: solo se actualizan las que cambiaron de texto.
            foreach ($nuevas as $indice => $texto) {
                $fila = $actuales->get($indice + 1);
                if ($fila !== null && $fila->pregunta !== $texto) {
                    $fila->update(['pregunta' => $texto, 'updated_by' => $actorId]);
                }
            }
        });

        return $this->obtener($competenciaId) ?? throw new PreguntasNoEncontradas;
    }

    // ------------------------------------------------------------------ Apoyo

    /** @return Builder<CompetenciaModel> */
    private function conRegistro(): Builder
    {
        return CompetenciaModel::query()
            ->whereHas('registroPreguntas')
            ->with(['registroPreguntas.preguntas']);
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

    private function aEntidad(CompetenciaModel $competencia, bool $conPreguntas): RegistroPreguntas
    {
        $registro = $competencia->registroPreguntas;

        return new RegistroPreguntas(
            competencia: $this->asociada($competencia),
            version: (int) $registro->version,
            creadoPor: $registro->created_by === null ? null : (int) $registro->created_by,
            modificadoPor: $registro->updated_by === null ? null : (int) $registro->updated_by,
            preguntas: $conPreguntas ? $registro->preguntas->pluck('pregunta')->all() : [],
        );
    }
}
