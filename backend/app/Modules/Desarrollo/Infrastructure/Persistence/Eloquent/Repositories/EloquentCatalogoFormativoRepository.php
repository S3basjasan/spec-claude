<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Desarrollo\Domain\Entities\ConfiguracionFormativa;
use App\Modules\Desarrollo\Domain\Exceptions\AreaNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\ConfiguracionModificada;
use App\Modules\Desarrollo\Domain\Exceptions\NombreDeAreaDuplicado;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ComparadorDeCambiosFormativos;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionHistorial;
use App\Modules\Desarrollo\Domain\ValueObjects\CambioHistorial;
use App\Modules\Desarrollo\Domain\ValueObjects\DatosConfiguracion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoRecurso;
use App\Modules\Desarrollo\Domain\ValueObjects\RecursoFormativo;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoCambioHistorial;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoRecurso;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AreaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaCursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaMicrocontenidoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\HistorialFormativoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentCatalogoFormativoRepository implements CatalogoFormativoRepositoryInterface
{
    /** A partir de esta cantidad de ids se lee todo en vez de usar `whereIn`. */
    private const UMBRAL_WHERE_IN = 500;

    public function __construct(private readonly ComparadorDeCambiosFormativos $comparador) {}

    public function configuracionDe(int $competenciaId): ConfiguracionFormativa
    {
        return $this->configuracionesDe([$competenciaId])[$competenciaId];
    }

    public function configuracionesDe(array $competenciaIds): array
    {
        $ids = array_values(array_unique($competenciaIds));
        if ($ids === []) {
            return [];
        }

        $filtrar = count($ids) <= self::UMBRAL_WHERE_IN;

        $filas = CompetenciaFormativaModel::query()->with('area')
            ->when($filtrar, fn ($q) => $q->whereIn('competencia_id', $ids))->get()->keyBy('competencia_id');
        $cursos = CompetenciaCursoModel::query()->with('curso')
            ->when($filtrar, fn ($q) => $q->whereIn('competencia_id', $ids))->orderBy('id')->get()->groupBy('competencia_id');
        $micros = CompetenciaMicrocontenidoModel::query()->with('microcontenido')
            ->when($filtrar, fn ($q) => $q->whereIn('competencia_id', $ids))->orderBy('id')->get()->groupBy('competencia_id');

        $resultado = [];
        foreach ($ids as $id) {
            /** @var CompetenciaFormativaModel|null $fila */
            $fila = $filas->get($id);

            $resultado[$id] = new ConfiguracionFormativa(
                $id,
                $fila?->area_formativa_id === null ? null : (int) $fila->area_formativa_id,
                $fila?->area?->nombre,
                (int) ($fila?->version ?? 0),
                $fila?->updated_by === null ? null : (int) $fila->updated_by,
                $fila?->updated_at?->toIso8601String(),
                ($cursos->get($id) ?? collect())
                    ->map(fn (CompetenciaCursoModel $v) => $this->recurso($v->curso, TipoRecurso::Curso))->all(),
                ($micros->get($id) ?? collect())
                    ->map(fn (CompetenciaMicrocontenidoModel $v) => $this->recurso($v->microcontenido, TipoRecurso::Microcontenido))->all(),
            );
        }

        return $resultado;
    }

    public function areas(): array
    {
        return AreaFormativaModel::query()->orderBy('nombre_clave')->get()
            ->map(fn (AreaFormativaModel $a) => ['id' => (int) $a->id, 'nombre' => $a->nombre])->all();
    }

    public function recursosDisponibles(TipoRecurso $tipo, ?string $q, ?int $excluirCompetenciaId): array
    {
        $clase = $tipo === TipoRecurso::Curso ? CursoModel::class : MicrocontenidoModel::class;
        $vinculos = $tipo === TipoRecurso::Curso ? CompetenciaCursoModel::class : CompetenciaMicrocontenidoModel::class;
        $columna = $tipo === TipoRecurso::Curso ? 'curso_id' : 'microcontenido_id';

        $usos = $vinculos::query()->select($columna, DB::raw('count(*) as total'))->groupBy($columna)->pluck('total', $columna);
        $yaVinculados = $excluirCompetenciaId === null
            ? collect()
            : $vinculos::query()->where('competencia_id', $excluirCompetenciaId)->pluck($columna);

        $busqueda = $q === null ? '' : $this->texto($q);

        return $clase::query()->where('estado', $this->estadoDisponible($tipo))->orderBy($this->columnaNombre($tipo))->get()
            ->reject(fn (Model $r) => $yaVinculados->contains($r->id))
            ->filter(fn (Model $r) => $busqueda === '' || str_contains($this->texto((string) $r->{$this->columnaNombre($tipo)}), $busqueda))
            ->map(fn (Model $r) => $this->recurso($r, $tipo, (int) ($usos[$r->id] ?? 0)))
            ->values()->all();
    }

    public function competenciasDeCursos(array $cursoIds): array
    {
        if ($cursoIds === []) {
            return [];
        }

        $mapa = [];
        foreach (CompetenciaCursoModel::query()->whereIn('curso_id', $cursoIds)->orderBy('id')->get(['curso_id', 'competencia_id']) as $vinculo) {
            $mapa[(int) $vinculo->curso_id][] = (int) $vinculo->competencia_id;
        }

        return $mapa;
    }

    public function idsDisponibles(TipoRecurso $tipo): array
    {
        $clase = $tipo === TipoRecurso::Curso ? CursoModel::class : MicrocontenidoModel::class;

        return array_map('intval', $clase::query()->where('estado', $this->estadoDisponible($tipo))->pluck('id')->all());
    }

    public function guardarConfiguracion(int $competenciaId, int $versionEsperada, DatosConfiguracion $datos, ?int $actorId): ConfiguracionFormativa
    {
        return DB::transaction(function () use ($competenciaId, $versionEsperada, $datos, $actorId) {
            $fila = CompetenciaFormativaModel::query()->where('competencia_id', $competenciaId)->first();
            if ((int) ($fila?->version ?? 0) !== $versionEsperada) {
                throw new ConfiguracionModificada;
            }

            $antes = $this->configuracionDe($competenciaId);

            $cursosAhora = CursoModel::query()->whereIn('id', $datos->cursoIds)->pluck('nombre', 'id')->all();
            $microsAhora = MicrocontenidoModel::query()->whereIn('id', $datos->microcontenidoIds)->pluck('titulo', 'id')->all();
            $nombreAreaNueva = $datos->areaFormativaId === null
                ? null
                : AreaFormativaModel::query()->whereKey($datos->areaFormativaId)->value('nombre');

            $cambios = $this->comparador->comparar(
                $competenciaId,
                $antes->areaFormativaId,
                $antes->areaNombre,
                $datos->areaFormativaId,
                $nombreAreaNueva,
                $this->nombresPorId($antes->cursos),
                $cursosAhora,
                $this->nombresPorId($antes->microcontenidos),
                $microsAhora,
            );

            if ($cambios === []) {
                return $antes;
            }

            $this->sincronizar(CompetenciaCursoModel::class, 'curso_id', $competenciaId, $datos->cursoIds, $actorId);
            $this->sincronizar(CompetenciaMicrocontenidoModel::class, 'microcontenido_id', $competenciaId, $datos->microcontenidoIds, $actorId);

            if ($fila === null) {
                CompetenciaFormativaModel::query()->create([
                    'competencia_id' => $competenciaId,
                    'area_formativa_id' => $datos->areaFormativaId,
                    'version' => 1,
                    'updated_by' => $actorId,
                ]);
            } else {
                $fila->forceFill([
                    'area_formativa_id' => $datos->areaFormativaId,
                    'version' => $fila->version + 1,
                    'updated_by' => $actorId,
                ])->save();
            }

            $this->registrar($cambios, $actorId);

            return $this->configuracionDe($competenciaId);
        });
    }

    public function crearArea(string $nombre, ?int $actorId): array
    {
        $nombre = AreaFormativaModel::normalizarNombre($nombre);
        $clave = AreaFormativaModel::claveDeNombre($nombre);

        if (AreaFormativaModel::query()->where('nombre_clave', $clave)->exists()) {
            throw new NombreDeAreaDuplicado;
        }

        try {
            $area = AreaFormativaModel::query()->create(['nombre' => $nombre, 'nombre_clave' => $clave]);
        } catch (QueryException) {
            throw new NombreDeAreaDuplicado;
        }

        return ['id' => (int) $area->id, 'nombre' => $area->nombre];
    }

    public function renombrarArea(int $areaId, string $nombre, ?int $actorId): array
    {
        return DB::transaction(function () use ($areaId, $nombre, $actorId) {
            $area = AreaFormativaModel::query()->find($areaId);
            if ($area === null) {
                throw new AreaNoEncontrada;
            }

            $nombre = AreaFormativaModel::normalizarNombre($nombre);
            $clave = AreaFormativaModel::claveDeNombre($nombre);

            if (AreaFormativaModel::query()->where('nombre_clave', $clave)->where('id', '!=', $areaId)->exists()) {
                throw new NombreDeAreaDuplicado;
            }

            if ($area->nombre !== $nombre) {
                $anterior = $area->nombre;
                $area->forceFill(['nombre' => $nombre, 'nombre_clave' => $clave])->save();

                $this->registrar([new CambioHistorial(
                    null,
                    TipoCambioHistorial::AreaFormativa,
                    AccionHistorial::Renombrado,
                    $areaId,
                    $anterior,
                    $nombre,
                )], $actorId);
            }

            return ['id' => (int) $area->id, 'nombre' => $area->nombre];
        });
    }

    public function historialDe(int $competenciaId, int $pagina, int $porPagina): array
    {
        $areasDeLaCompetencia = HistorialFormativoModel::query()
            ->where('competencia_id', $competenciaId)
            ->where('tipo_cambio', TipoCambioHistorial::AreaFormativa->value)
            ->whereNotNull('recurso_id')->pluck('recurso_id')->all();
        $areaActual = CompetenciaFormativaModel::query()->where('competencia_id', $competenciaId)->value('area_formativa_id');
        if ($areaActual !== null) {
            $areasDeLaCompetencia[] = $areaActual;
        }

        $consulta = HistorialFormativoModel::query()->where(function ($q) use ($competenciaId, $areasDeLaCompetencia) {
            $q->where('competencia_id', $competenciaId)
                ->orWhere(function ($r) use ($areasDeLaCompetencia) {
                    $r->whereNull('competencia_id')
                        ->where('accion', AccionHistorial::Renombrado->value)
                        ->whereIn('recurso_id', array_unique($areasDeLaCompetencia));
                });
        });

        $total = (clone $consulta)->count();
        $items = $consulta->orderByDesc('id')->forPage($pagina, $porPagina)->get()
            ->map(fn (HistorialFormativoModel $h) => [
                'fecha' => $h->created_at?->toIso8601String() ?? '',
                'usuario_id' => $h->user_id === null ? null : (int) $h->user_id,
                'tipo_cambio' => $h->tipo_cambio,
                'accion' => $h->accion,
                'recurso' => $h->tipo_cambio === TipoCambioHistorial::AreaFormativa->value ? null : ($h->valor_nuevo ?? $h->valor_anterior),
                'valor_anterior' => $h->valor_anterior,
                'valor_nuevo' => $h->valor_nuevo,
            ])->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param  array<int, RecursoFormativo>  $recursos
     * @return array<int, string>
     */
    private function nombresPorId(array $recursos): array
    {
        $mapa = [];
        foreach ($recursos as $r) {
            $mapa[$r->id] = $r->nombre;
        }

        return $mapa;
    }

    /**
     * Deja los vínculos de la competencia exactamente como `$ids` (altas y bajas).
     *
     * @param  class-string<Model>  $clase
     * @param  array<int, int>  $ids
     */
    private function sincronizar(string $clase, string $columna, int $competenciaId, array $ids, ?int $actorId): void
    {
        $actuales = $clase::query()->where('competencia_id', $competenciaId)->pluck($columna)->map(fn ($v) => (int) $v)->all();

        $clase::query()->where('competencia_id', $competenciaId)->whereNotIn($columna, $ids)->delete();

        foreach (array_diff($ids, $actuales) as $id) {
            $clase::query()->create(['competencia_id' => $competenciaId, $columna => $id, 'created_by' => $actorId]);
        }
    }

    /**
     * @param  array<int, CambioHistorial>  $cambios
     */
    private function registrar(array $cambios, ?int $actorId): void
    {
        foreach ($cambios as $c) {
            HistorialFormativoModel::query()->create([
                'competencia_id' => $c->competenciaId,
                'tipo_cambio' => $c->tipoCambio->value,
                'accion' => $c->accion->value,
                'recurso_id' => $c->recursoId,
                'valor_anterior' => $c->valorAnterior,
                'valor_nuevo' => $c->valorNuevo,
                'user_id' => $actorId,
            ]);
        }
    }

    private function recurso(Model $modelo, TipoRecurso $tipo, int $competenciasCount = 0): RecursoFormativo
    {
        if ($tipo === TipoRecurso::Microcontenido) {
            // Micro-learning (016): solo Publicado está disponible; Borrador y Archivado no cuentan para el Catálogo.
            return new RecursoFormativo(
                (int) $modelo->id,
                $tipo,
                (string) $modelo->titulo,
                (string) $modelo->tipo,
                (int) $modelo->duracion_minutos,
                null,
                $modelo->estado === 'Publicado' ? EstadoRecurso::Disponible : EstadoRecurso::Archivado,
                '/micro-learning/'.$modelo->id,
                $competenciasCount,
            );
        }

        return new RecursoFormativo(
            (int) $modelo->id,
            $tipo,
            (string) $modelo->nombre,
            (string) $modelo->tipo,
            (int) $modelo->duracion_minutos,
            $modelo->formador,
            EstadoRecurso::from((string) $modelo->estado),
            $modelo->url_destino,
            $competenciasCount,
        );
    }

    /** Valor de `estado` que hace «disponible» a un recurso: los cursos usan Disponible y los micro-contenidos, Publicado. */
    private function estadoDisponible(TipoRecurso $tipo): string
    {
        return $tipo === TipoRecurso::Curso ? EstadoRecurso::Disponible->value : 'Publicado';
    }

    private function columnaNombre(TipoRecurso $tipo): string
    {
        return $tipo === TipoRecurso::Curso ? 'nombre' : 'titulo';
    }

    public function sincronizarCompetenciasDeMicrocontenido(int $microcontenidoId, array $competenciaIds, ?int $actorId): void
    {
        $ids = array_values(array_unique(array_map('intval', $competenciaIds)));
        $titulo = (string) MicrocontenidoModel::query()->whereKey($microcontenidoId)->value('titulo');
        $actuales = CompetenciaMicrocontenidoModel::query()->where('microcontenido_id', $microcontenidoId)->pluck('competencia_id')->map(fn ($v) => (int) $v)->all();

        $quitadas = array_values(array_diff($actuales, $ids));
        $nuevas = array_values(array_diff($ids, $actuales));

        if ($quitadas !== []) {
            CompetenciaMicrocontenidoModel::query()->where('microcontenido_id', $microcontenidoId)->whereIn('competencia_id', $quitadas)->delete();
        }
        foreach ($nuevas as $competenciaId) {
            CompetenciaMicrocontenidoModel::query()->create(['competencia_id' => $competenciaId, 'microcontenido_id' => $microcontenidoId, 'created_by' => $actorId]);
        }

        $cambios = [];
        foreach ($nuevas as $competenciaId) {
            $cambios[] = new CambioHistorial($competenciaId, TipoCambioHistorial::Microcontenido, AccionHistorial::Asociado, $microcontenidoId, null, $titulo);
        }
        foreach ($quitadas as $competenciaId) {
            $cambios[] = new CambioHistorial($competenciaId, TipoCambioHistorial::Microcontenido, AccionHistorial::Quitado, $microcontenidoId, $titulo, null);
        }

        // Sube la versión de cada configuración afectada para que un panel abierto del Catálogo no sobrescriba el cambio.
        foreach (array_merge($nuevas, $quitadas) as $competenciaId) {
            $fila = CompetenciaFormativaModel::query()->where('competencia_id', $competenciaId)->first();
            if ($fila === null) {
                CompetenciaFormativaModel::query()->create(['competencia_id' => $competenciaId, 'area_formativa_id' => null, 'version' => 1, 'updated_by' => $actorId]);
            } else {
                $fila->forceFill(['version' => $fila->version + 1, 'updated_by' => $actorId])->save();
            }
        }

        $this->registrar($cambios, $actorId);
    }

    /** Texto sin tildes ni mayúsculas para comparar búsquedas. */
    private function texto(string $valor): string
    {
        return Str::lower(Str::ascii(trim($valor)));
    }
}
