<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Desarrollo\Domain\Entities\Microcontenido;
use App\Modules\Desarrollo\Domain\Exceptions\MicroConProgreso;
use App\Modules\Desarrollo\Domain\Exceptions\MicroNoEncontrado;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\MicrocontenidoRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\DatosMicro;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroMicro;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoMicro;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaMicrocontenidoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroIntentoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroOpcionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroPreguntaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroProgresoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroRespuestaModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentMicrocontenidoRepository implements MicrocontenidoRepositoryInterface
{
    public function __construct(private readonly CatalogoFormativoRepositoryInterface $catalogo) {}

    public function crear(DatosMicro $datos, ?int $actorId): int
    {
        return DB::transaction(function () use ($datos, $actorId) {
            $micro = MicrocontenidoModel::query()->create([
                'titulo' => $datos->titulo,
                'tipo' => $datos->tipo->value,
                'url' => $datos->tipo->requiereUrl() ? $datos->url : null,
                'miniatura_url' => $datos->miniaturaUrl,
                'duracion_minutos' => $datos->duracionMinutos,
                'estado' => EstadoMicro::Borrador->value,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            // Como `id()` es AUTOINCREMENT, un id eliminado no se reutiliza y el código nunca se repite.
            $micro->forceFill(['codigo' => 'MICRO-'.str_pad((string) $micro->id, 4, '0', STR_PAD_LEFT)])->save();

            MicroHistorialModel::query()->create([
                'microcontenido_id' => $micro->id,
                'estado_anterior' => null,
                'estado_nuevo' => EstadoMicro::Borrador->value,
                'user_id' => $actorId,
            ]);

            if ($datos->tipo->esQuiz() && $datos->preguntas !== null) {
                $this->insertarPreguntas((int) $micro->id, $datos->preguntas);
            }

            $this->catalogo->sincronizarCompetenciasDeMicrocontenido((int) $micro->id, $datos->competenciaIds, $actorId);

            return (int) $micro->id;
        });
    }

    public function actualizar(int $id, DatosMicro $datos, ?int $actorId): void
    {
        DB::transaction(function () use ($id, $datos, $actorId) {
            $micro = MicrocontenidoModel::query()->find($id);
            if ($micro === null) {
                throw new MicroNoEncontrado;
            }

            $micro->forceFill([
                'titulo' => $datos->titulo,
                'url' => $datos->tipo->requiereUrl() ? $datos->url : null,
                'miniatura_url' => $datos->miniaturaUrl,
                'duracion_minutos' => $datos->duracionMinutos,
                'updated_by' => $actorId,
            ])->save();

            if ($datos->tipo->esQuiz() && $datos->preguntas !== null) {
                $this->descartarIntentosEnCurso($id);
                $this->borrarPreguntas($id);
                $this->insertarPreguntas($id, $datos->preguntas);
            }

            $this->catalogo->sincronizarCompetenciasDeMicrocontenido($id, $datos->competenciaIds, $actorId);
        });
    }

    public function obtener(int $id): ?Microcontenido
    {
        $micro = MicrocontenidoModel::query()->with(['preguntas.opciones'])->find($id);
        if ($micro === null) {
            return null;
        }

        return $this->entidades([$micro], true)[0];
    }

    public function obtenerVarios(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $modelos = MicrocontenidoModel::query()->with(['preguntas.opciones'])->whereIn('id', array_values(array_unique($ids)))->get();
        $mapa = [];
        foreach ($this->entidades($modelos->all(), true) as $e) {
            $mapa[$e->id] = $e;
        }

        return $mapa;
    }

    public function listar(FiltroMicro $filtro): array
    {
        $consulta = MicrocontenidoModel::query();

        if ($filtro->tipo !== null) {
            $consulta->where('tipo', $filtro->tipo->value);
        }
        if ($filtro->estados !== []) {
            $consulta->whereIn('estado', array_map(fn (EstadoMicro $e) => $e->value, $filtro->estados));
        }
        if ($filtro->competenciaId !== null) {
            $consulta->whereIn('id', CompetenciaMicrocontenidoModel::query()->where('competencia_id', $filtro->competenciaId)->select('microcontenido_id'));
        }

        $consulta->orderByDesc('id');

        if ($filtro->q !== null && trim($filtro->q) !== '') {
            // Búsqueda sin tildes ni mayúsculas: se compara en PHP sobre los candidatos ya filtrados.
            $busqueda = $this->texto($filtro->q);
            $ids = $consulta->get(['id', 'titulo'])
                ->filter(fn (MicrocontenidoModel $m) => str_contains($this->texto((string) $m->titulo), $busqueda))
                ->pluck('id')->map(fn ($v) => (int) $v)->all();
            $total = count($ids);
            $pagina = array_slice($ids, ($filtro->pagina - 1) * $filtro->porPagina, $filtro->porPagina);
            $modelos = $pagina === [] ? collect() : MicrocontenidoModel::query()->whereIn('id', $pagina)->orderByDesc('id')->get();
        } else {
            $total = (clone $consulta)->count();
            $modelos = $consulta->forPage($filtro->pagina, $filtro->porPagina)->get();
        }

        return ['items' => $this->entidades($modelos->all(), false), 'total' => $total];
    }

    public function cambiarEstado(int $id, EstadoMicro $nuevo, ?int $actorId): void
    {
        DB::transaction(function () use ($id, $nuevo, $actorId) {
            $micro = MicrocontenidoModel::query()->find($id);
            if ($micro === null) {
                throw new MicroNoEncontrado;
            }

            $anterior = (string) $micro->estado;
            $micro->forceFill(['estado' => $nuevo->value, 'updated_by' => $actorId])->save();

            MicroHistorialModel::query()->create([
                'microcontenido_id' => $id,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $nuevo->value,
                'user_id' => $actorId,
            ]);
        });
    }

    public function eliminar(int $id, ?int $actorId): void
    {
        DB::transaction(function () use ($id, $actorId) {
            $micro = MicrocontenidoModel::query()->find($id);
            if ($micro === null) {
                throw new MicroNoEncontrado;
            }
            if (MicroProgresoModel::query()->where('microcontenido_id', $id)->exists()) {
                throw new MicroConProgreso;
            }

            $this->borrarPreguntas($id);
            MicroHistorialModel::query()->where('microcontenido_id', $id)->delete();
            $this->catalogo->sincronizarCompetenciasDeMicrocontenido($id, [], $actorId);
            $micro->delete();
        });
    }

    public function historial(int $id, int $pagina, int $porPagina): array
    {
        $consulta = MicroHistorialModel::query()->where('microcontenido_id', $id);
        $total = (clone $consulta)->count();
        $items = $consulta->orderByDesc('id')->forPage($pagina, $porPagina)->get()
            ->map(fn (MicroHistorialModel $h) => [
                'id' => (int) $h->id,
                'estado_anterior' => $h->estado_anterior,
                'estado_nuevo' => (string) $h->estado_nuevo,
                'user_id' => $h->user_id === null ? null : (int) $h->user_id,
                'fecha' => $h->created_at?->toIso8601String(),
            ])->all();

        return ['items' => $items, 'total' => $total];
    }

    public function publicadosDeCompetencia(int $competenciaId): array
    {
        $modelos = MicrocontenidoModel::query()
            ->where('estado', EstadoMicro::Publicado->value)
            ->whereIn('id', CompetenciaMicrocontenidoModel::query()->where('competencia_id', $competenciaId)->select('microcontenido_id'))
            ->orderBy('id')->get();

        return $this->entidades($modelos->all(), false);
    }

    /**
     * Arma las entidades con una consulta por dato derivado (vínculos, completaciones, progreso e intentos).
     *
     * @param  array<int, MicrocontenidoModel>  $modelos
     * @return array<int, Microcontenido>
     */
    private function entidades(array $modelos, bool $conPreguntas): array
    {
        if ($modelos === []) {
            return [];
        }

        $ids = array_map(fn (MicrocontenidoModel $m) => (int) $m->id, $modelos);

        $vinculos = CompetenciaMicrocontenidoModel::query()->whereIn('microcontenido_id', $ids)->orderBy('id')->get(['microcontenido_id', 'competencia_id'])
            ->groupBy('microcontenido_id');
        $completaciones = MicroProgresoModel::query()->whereIn('microcontenido_id', $ids)->where('estado', 'Completado')
            ->select('microcontenido_id', DB::raw('count(*) as total'))->groupBy('microcontenido_id')->pluck('total', 'microcontenido_id');
        $conProgreso = MicroProgresoModel::query()->whereIn('microcontenido_id', $ids)->distinct()->pluck('microcontenido_id')->map(fn ($v) => (int) $v)->all();
        $conIntentos = MicroIntentoModel::query()->where('microcontenido_intentos.estado', 'Finalizado')
            ->join('microcontenido_progresos', 'microcontenido_progresos.id', '=', 'microcontenido_intentos.progreso_id')
            ->whereIn('microcontenido_progresos.microcontenido_id', $ids)
            ->distinct()->pluck('microcontenido_progresos.microcontenido_id')->map(fn ($v) => (int) $v)->all();

        return array_map(function (MicrocontenidoModel $m) use ($vinculos, $completaciones, $conProgreso, $conIntentos, $conPreguntas) {
            $id = (int) $m->id;

            return new Microcontenido(
                $id,
                (string) $m->codigo,
                (string) $m->titulo,
                TipoMicro::from((string) $m->tipo),
                $m->url,
                $m->miniatura_url,
                (int) $m->duracion_minutos,
                EstadoMicro::from((string) $m->estado),
                ($vinculos->get($id) ?? collect())->pluck('competencia_id')->map(fn ($v) => (int) $v)->all(),
                $conPreguntas ? $this->preguntasDe($m) : [],
                $m->created_by === null ? null : (int) $m->created_by,
                $m->created_at?->toIso8601String(),
                $m->updated_by === null ? null : (int) $m->updated_by,
                $m->updated_at?->toIso8601String(),
                (int) ($completaciones[$id] ?? 0),
                in_array($id, $conProgreso, true),
                in_array($id, $conIntentos, true),
            );
        }, $modelos);
    }

    /**
     * @return array<int, array{id: int, enunciado: string, orden: int, opciones: array<int, array{id: int, texto: string, orden: int, es_correcta: bool}>}>
     */
    private function preguntasDe(MicrocontenidoModel $m): array
    {
        return $m->preguntas->map(fn (MicroPreguntaModel $p) => [
            'id' => (int) $p->id,
            'enunciado' => (string) $p->enunciado,
            'orden' => (int) $p->orden,
            'opciones' => $p->opciones->map(fn (MicroOpcionModel $o) => [
                'id' => (int) $o->id,
                'texto' => (string) $o->texto,
                'orden' => (int) $o->orden,
                'es_correcta' => (bool) $o->es_correcta,
            ])->all(),
        ])->all();
    }

    /**
     * @param  array<int, array{enunciado: string, opciones: array<int, array{texto: string, es_correcta: bool}>}>  $preguntas
     */
    private function insertarPreguntas(int $microId, array $preguntas): void
    {
        foreach (array_values($preguntas) as $i => $pregunta) {
            $modelo = MicroPreguntaModel::query()->create([
                'microcontenido_id' => $microId,
                'enunciado' => $pregunta['enunciado'],
                'orden' => $i + 1,
            ]);

            foreach (array_values($pregunta['opciones']) as $j => $opcion) {
                MicroOpcionModel::query()->create([
                    'pregunta_id' => $modelo->id,
                    'texto' => $opcion['texto'],
                    'es_correcta' => (bool) $opcion['es_correcta'],
                    'orden' => $j + 1,
                ]);
            }
        }
    }

    /** Borrado explícito (sin cascade): opciones y luego preguntas. */
    private function borrarPreguntas(int $microId): void
    {
        $preguntaIds = MicroPreguntaModel::query()->where('microcontenido_id', $microId)->pluck('id');
        MicroOpcionModel::query()->whereIn('pregunta_id', $preguntaIds)->delete();
        MicroPreguntaModel::query()->where('microcontenido_id', $microId)->delete();
    }

    /** Descarta, con sus respuestas, los intentos En curso de todos los progresos del contenido. */
    private function descartarIntentosEnCurso(int $microId): void
    {
        $progresoIds = MicroProgresoModel::query()->where('microcontenido_id', $microId)->pluck('id');
        $intentoIds = MicroIntentoModel::query()->whereIn('progreso_id', $progresoIds)->where('estado', 'EnCurso')->pluck('id');
        MicroRespuestaModel::query()->whereIn('intento_id', $intentoIds)->delete();
        MicroIntentoModel::query()->whereIn('id', $intentoIds)->delete();
    }

    /** Texto sin tildes ni mayúsculas para comparar búsquedas. */
    private function texto(string $valor): string
    {
        return Str::lower(Str::ascii(trim($valor)));
    }
}
