<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Desarrollo\Domain\Entities\EventoDeRuta;
use App\Modules\Desarrollo\Domain\Entities\InscripcionARuta;
use App\Modules\Desarrollo\Domain\Entities\NivelDeRuta;
use App\Modules\Desarrollo\Domain\Entities\Ruta;
use App\Modules\Desarrollo\Domain\Exceptions\ConflictoDeVersionRuta;
use App\Modules\Desarrollo\Domain\Exceptions\InscripcionNoPermitida;
use App\Modules\Desarrollo\Domain\Exceptions\NivelEnUso;
use App\Modules\Desarrollo\Domain\Exceptions\RutaInvalida;
use App\Modules\Desarrollo\Domain\Exceptions\RutaNoEncontrada;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\CodigoDeRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeInscripcion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\NombreDeRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeRuta;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaNivelModel;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentRutasRepository implements RutasRepositoryInterface
{
    public function crear(Ruta $ruta, array $eventos): Ruta
    {
        $clave = (new NombreDeRuta($ruta->nombre))->clave();

        for ($intento = 1; ; $intento++) {
            try {
                $id = DB::transaction(function () use ($ruta, $eventos, $clave): int {
                    $numero = (int) RutaModel::query()->where('tenant_id', $ruta->tenantId)->max('numero') + 1;
                    $modelo = RutaModel::query()->create([
                        'tenant_id' => $ruta->tenantId,
                        'numero' => $numero,
                        'codigo' => CodigoDeRuta::desde($numero),
                        'nombre' => $ruta->nombre,
                        'nombre_clave' => $clave,
                        'tipo' => $ruta->tipo->value,
                        'descripcion' => $ruta->descripcion,
                        'departamento_id' => $ruta->departamentoId,
                        'estado' => EstadoDeRuta::Borrador->value,
                        'version' => 1,
                        'created_by' => $ruta->creadoPor,
                        'updated_by' => $ruta->actualizadoPor,
                    ]);
                    foreach ($ruta->niveles as $nivel) {
                        RutaNivelModel::query()->create($this->datosDeNivel($ruta->tenantId, (int) $modelo->id, $nivel));
                    }
                    $this->registrar($ruta->tenantId, (int) $modelo->id, $eventos);

                    return (int) $modelo->id;
                });

                return $this->obtener($ruta->tenantId, $id) ?? $ruta;
            } catch (UniqueConstraintViolationException $e) {
                if ($this->existeNombre($ruta->tenantId, $clave)) {
                    throw RutaInvalida::enCampo('nombre', 'Ya existe una ruta con ese nombre.', 'nombre_duplicado');
                }
                if ($intento >= 3) {
                    throw $e;
                }
            }
        }
    }

    public function obtener(int $tenantId, int $id): ?Ruta
    {
        $m = RutaModel::query()->with('niveles')->where('tenant_id', $tenantId)->whereKey($id)->first();

        return $m === null ? null : $this->aRuta($m);
    }

    public function guardar(Ruta $ruta, int $versionEsperada, array $eventos): Ruta
    {
        $clave = (new NombreDeRuta($ruta->nombre))->clave();

        try {
            DB::transaction(function () use ($ruta, $versionEsperada, $eventos, $clave): void {
                $m = RutaModel::query()->where('tenant_id', $ruta->tenantId)->whereKey($ruta->id)->lockForUpdate()->first()
                    ?? throw new RutaNoEncontrada;
                if ((int) $m->version !== $versionEsperada) {
                    throw new ConflictoDeVersionRuta;
                }

                $existentes = RutaNivelModel::query()->where('ruta_id', $m->id)->get()->keyBy('id');
                $entrantes = [];
                foreach ($ruta->niveles as $nivel) {
                    if ($nivel->id !== null) {
                        if (! $existentes->has($nivel->id)) {
                            throw RutaInvalida::enCampo('niveles', 'Hay un nivel que no pertenece a la ruta.');
                        }
                        $entrantes[] = $nivel->id;
                    }
                }

                $quitar = array_values(array_diff($existentes->keys()->map(fn ($i) => (int) $i)->all(), $entrantes));
                if ($quitar !== []) {
                    $enUso = $this->nivelesEnUso((int) $m->id);
                    $conHistorial = $this->nivelesConInscripciones((int) $m->id);
                    foreach ($quitar as $nivelId) {
                        if (isset($enUso[$nivelId])) {
                            throw new NivelEnUso('No se puede quitar un nivel que es nivel actual u objetivo de una inscripción activa.');
                        }
                        if (in_array($nivelId, $conHistorial, true)) {
                            throw new NivelEnUso('El nivel conserva historial de inscripciones y no puede quitarse.', 'nivel_con_historial');
                        }
                    }
                    RutaNivelModel::query()->whereIn('id', $quitar)->delete();
                }

                // Valores temporales negativos para respetar los únicos (ruta, orden) y (ruta, puesto) al reordenar o intercambiar puestos.
                foreach ($entrantes as $nivelId) {
                    RutaNivelModel::query()->whereKey($nivelId)->update(['orden' => -$nivelId, 'puesto_id' => -$nivelId]);
                }
                foreach ($ruta->niveles as $nivel) {
                    $datos = $this->datosDeNivel($ruta->tenantId, (int) $m->id, $nivel);
                    if ($nivel->id === null) {
                        RutaNivelModel::query()->create($datos);
                    } else {
                        RutaNivelModel::query()->whereKey($nivel->id)->update([
                            'orden' => $nivel->orden,
                            'puesto_id' => $nivel->puestoId,
                            'nombre' => $nivel->nombre,
                            'experiencia_minima_anios' => $nivel->experienciaMinimaAnios,
                        ]);
                    }
                }

                $m->update([
                    'nombre' => $ruta->nombre,
                    'nombre_clave' => $clave,
                    'tipo' => $ruta->tipo->value,
                    'descripcion' => $ruta->descripcion,
                    'departamento_id' => $ruta->departamentoId,
                    'version' => $versionEsperada + 1,
                    'updated_by' => $ruta->actualizadoPor,
                ]);
                $this->registrar($ruta->tenantId, (int) $m->id, $eventos);
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($this->existeNombre($ruta->tenantId, $clave, $ruta->id)) {
                throw RutaInvalida::enCampo('nombre', 'Ya existe una ruta con ese nombre.', 'nombre_duplicado');
            }
            throw RutaInvalida::enCampo('niveles', 'Los niveles no pueden repetir orden ni puesto.');
        }

        return $this->obtener($ruta->tenantId, (int) $ruta->id) ?? $ruta;
    }

    public function cambiarEstado(int $tenantId, int $rutaId, int $versionEsperada, EstadoDeRuta $estado, int $usuarioId, EventoDeRuta $evento): Ruta
    {
        DB::transaction(function () use ($tenantId, $rutaId, $versionEsperada, $estado, $usuarioId, $evento): void {
            $m = RutaModel::query()->where('tenant_id', $tenantId)->whereKey($rutaId)->lockForUpdate()->first()
                ?? throw new RutaNoEncontrada;
            if ((int) $m->version !== $versionEsperada) {
                throw new ConflictoDeVersionRuta;
            }
            $m->update(['estado' => $estado->value, 'version' => $versionEsperada + 1, 'updated_by' => $usuarioId]);
            $this->registrar($tenantId, $rutaId, [$evento]);
        });

        return $this->obtener($tenantId, $rutaId) ?? throw new RutaNoEncontrada;
    }

    public function existeNombre(int $tenantId, string $clave, ?int $exceptoId = null): bool
    {
        return RutaModel::query()->where('tenant_id', $tenantId)->where('nombre_clave', $clave)
            ->when($exceptoId !== null, fn ($q) => $q->where('id', '!=', $exceptoId))->exists();
    }

    public function listar(int $tenantId, array $filtro, int $pagina, int $porPagina): array
    {
        $q = RutaModel::query()->where('tenant_id', $tenantId);
        if (($filtro['solo_vigentes'] ?? false) === true) {
            $q->where('estado', EstadoDeRuta::Vigente->value);
        }
        if (! empty($filtro['tipo'])) {
            $q->where('tipo', $filtro['tipo']);
        }
        if (! empty($filtro['estado'])) {
            $q->where('estado', $filtro['estado']);
        }
        if (! empty($filtro['departamento_id'])) {
            $q->where('departamento_id', $filtro['departamento_id']);
        }
        if (! empty($filtro['q'])) {
            $q->where('nombre_clave', 'like', '%'.mb_strtolower(trim((string) $filtro['q'])).'%');
        }

        $total = (clone $q)->count();
        $filas = $q->orderBy('numero')->forPage($pagina, $porPagina)->get();
        $ids = $filas->pluck('id')->all();
        $niveles = RutaNivelModel::query()->whereIn('ruta_id', $ids)->selectRaw('ruta_id, count(*) as c')->groupBy('ruta_id')->pluck('c', 'ruta_id');
        $inscritos = RutaInscripcionModel::query()->whereIn('ruta_id', $ids)->where('estado', EstadoDeInscripcion::Activa->value)
            ->selectRaw('ruta_id, count(*) as c')->groupBy('ruta_id')->pluck('c', 'ruta_id');

        return [
            'items' => $filas->map(fn (RutaModel $m) => [
                'id' => (int) $m->id,
                'codigo' => (string) $m->codigo,
                'nombre' => (string) $m->nombre,
                'tipo' => (string) $m->tipo,
                'descripcion' => $m->descripcion,
                'estado' => (string) $m->estado,
                'departamento_id' => $m->departamento_id === null ? null : (int) $m->departamento_id,
                'niveles_count' => (int) ($niveles[$m->id] ?? 0),
                'inscritos_count' => (int) ($inscritos[$m->id] ?? 0),
            ])->all(),
            'total' => $total,
        ];
    }

    public function vigentes(int $tenantId): array
    {
        $filas = RutaModel::query()->where('tenant_id', $tenantId)->where('estado', EstadoDeRuta::Vigente->value)->orderBy('numero')->get();
        $niveles = RutaNivelModel::query()->whereIn('ruta_id', $filas->pluck('id')->all())->selectRaw('ruta_id, count(*) as c')->groupBy('ruta_id')->pluck('c', 'ruta_id');

        return $filas->map(fn (RutaModel $m) => [
            'id' => (int) $m->id,
            'codigo' => (string) $m->codigo,
            'nombre' => (string) $m->nombre,
            'tipo' => (string) $m->tipo,
            'niveles_count' => (int) ($niveles[$m->id] ?? 0),
        ])->all();
    }

    public function nivelesEnUso(int $rutaId): array
    {
        $uso = [];
        $activas = RutaInscripcionModel::query()->where('ruta_id', $rutaId)->where('estado', EstadoDeInscripcion::Activa->value)->get(['nivel_actual_id', 'nivel_objetivo_id']);
        foreach ($activas as $i) {
            foreach ([$i->nivel_actual_id, $i->nivel_objetivo_id] as $nivelId) {
                if ($nivelId !== null) {
                    $uso[(int) $nivelId] = ($uso[(int) $nivelId] ?? 0) + 1;
                }
            }
        }

        return $uso;
    }

    public function nivelesConInscripciones(int $rutaId): array
    {
        $ids = [];
        foreach (RutaInscripcionModel::query()->where('ruta_id', $rutaId)->get(['nivel_actual_id', 'nivel_objetivo_id']) as $i) {
            foreach ([$i->nivel_actual_id, $i->nivel_objetivo_id] as $nivelId) {
                if ($nivelId !== null) {
                    $ids[(int) $nivelId] = true;
                }
            }
        }

        return array_keys($ids);
    }

    public function activasDeRuta(int $rutaId): array
    {
        return RutaInscripcionModel::query()->where('ruta_id', $rutaId)->where('estado', EstadoDeInscripcion::Activa->value)->orderBy('id')->get()
            ->map(fn (RutaInscripcionModel $m) => $this->aInscripcion($m))->all();
    }

    public function crearInscripcion(InscripcionARuta $inscripcion, EventoDeRuta $evento): InscripcionARuta
    {
        try {
            return DB::transaction(function () use ($inscripcion, $evento): InscripcionARuta {
                $m = RutaInscripcionModel::query()->create([
                    'tenant_id' => $inscripcion->tenantId,
                    'ruta_id' => $inscripcion->rutaId,
                    'colaborador_id' => $inscripcion->colaboradorId,
                    'nivel_actual_id' => $inscripcion->nivelActualId,
                    'nivel_objetivo_id' => $inscripcion->nivelObjetivoId,
                    'estado' => EstadoDeInscripcion::Activa->value,
                    'created_by' => $inscripcion->creadoPor,
                ]);
                $this->registrar($inscripcion->tenantId, $inscripcion->rutaId, [$evento->conInscripcion((int) $m->id)]);

                return $this->aInscripcion($m);
            });
        } catch (UniqueConstraintViolationException) {
            throw new InscripcionNoPermitida('El colaborador ya tiene una inscripción activa en esta ruta.', 'ya_inscrito', 'colaborador_id');
        }
    }

    public function obtenerInscripcion(int $tenantId, int $id): ?InscripcionARuta
    {
        $m = RutaInscripcionModel::query()->where('tenant_id', $tenantId)->whereKey($id)->first();

        return $m === null ? null : $this->aInscripcion($m);
    }

    public function guardarInscripcion(InscripcionARuta $inscripcion, EventoDeRuta $evento): InscripcionARuta
    {
        return DB::transaction(function () use ($inscripcion, $evento): InscripcionARuta {
            RutaInscripcionModel::query()->whereKey($inscripcion->id)->update([
                'nivel_actual_id' => $inscripcion->nivelActualId,
                'nivel_objetivo_id' => $inscripcion->nivelObjetivoId,
                'estado' => $inscripcion->estado->value,
                'motivo' => $inscripcion->motivo,
                'resuelta_by' => $inscripcion->resueltaPor,
                'resuelta_at' => $inscripcion->resueltaEn?->format('Y-m-d H:i:s'),
                'updated_at' => now(),
            ]);
            $this->registrar($inscripcion->tenantId, $inscripcion->rutaId, [$evento->conInscripcion((int) $inscripcion->id)]);

            return $this->aInscripcion(RutaInscripcionModel::query()->findOrFail($inscripcion->id));
        });
    }

    public function listarInscripciones(int $tenantId, array $filtro, int $pagina, int $porPagina): array
    {
        $q = RutaInscripcionModel::query()->where('tenant_id', $tenantId);
        if (! empty($filtro['ruta_id'])) {
            $q->where('ruta_id', $filtro['ruta_id']);
        }
        if (! empty($filtro['colaborador_id'])) {
            $q->where('colaborador_id', $filtro['colaborador_id']);
        }
        if (! empty($filtro['estado'])) {
            $q->where('estado', $filtro['estado']);
        }

        $total = (clone $q)->count();
        $items = $q->orderByDesc('id')->forPage($pagina, $porPagina)->get()->map(fn (RutaInscripcionModel $m) => $this->aInscripcion($m))->all();

        return ['items' => $items, 'total' => $total];
    }

    public function activasDeColaborador(int $tenantId, int $colaboradorId): array
    {
        return $this->activasDeColaboradores($tenantId, [$colaboradorId]);
    }

    public function colaboradoresConActivas(int $tenantId): array
    {
        return RutaInscripcionModel::query()->where('tenant_id', $tenantId)->where('estado', EstadoDeInscripcion::Activa->value)
            ->distinct()->pluck('colaborador_id')->map(fn ($i) => (int) $i)->all();
    }

    public function activasDeColaboradores(int $tenantId, array $colaboradorIds): array
    {
        if ($colaboradorIds === []) {
            return [];
        }

        return RutaInscripcionModel::query()->where('tenant_id', $tenantId)->where('estado', EstadoDeInscripcion::Activa->value)
            ->whereIn('colaborador_id', $colaboradorIds)->orderBy('id')->get()
            ->map(fn (RutaInscripcionModel $m) => $this->aInscripcion($m))->all();
    }

    public function nivelPorId(int $tenantId, int $nivelId): ?array
    {
        $n = RutaNivelModel::query()->where('tenant_id', $tenantId)->whereKey($nivelId)->first();
        if ($n === null) {
            return null;
        }
        $r = RutaModel::query()->find($n->ruta_id);

        return [
            'nivel_id' => (int) $n->id,
            'ruta_id' => (int) $n->ruta_id,
            'ruta_codigo' => (string) $r?->codigo,
            'ruta_nombre' => (string) $r?->nombre,
            'ruta_tipo' => (string) $r?->tipo,
            'orden' => (int) $n->orden,
            'puesto_id' => (int) $n->puesto_id,
            'nombre' => (string) $n->nombre,
            'experiencia_minima_anios' => $n->experiencia_minima_anios,
        ];
    }

    public function historial(int $tenantId, int $rutaId, int $pagina, int $porPagina): array
    {
        $q = RutaHistorialModel::query()->where('tenant_id', $tenantId)->where('ruta_id', $rutaId);
        $total = (clone $q)->count();
        $items = $q->orderByDesc('id')->forPage($pagina, $porPagina)->get()->map(fn (RutaHistorialModel $h) => [
            'id' => (int) $h->id,
            'tipo' => (string) $h->tipo,
            'accion' => (string) $h->accion,
            'inscripcion_id' => $h->inscripcion_id === null ? null : (int) $h->inscripcion_id,
            'valor_anterior' => $h->valor_anterior,
            'valor_nuevo' => $h->valor_nuevo,
            'motivo' => $h->motivo,
            'user_id' => $h->user_id === null ? null : (int) $h->user_id,
            'created_at' => $h->created_at?->toIso8601String(),
        ])->all();

        return ['items' => $items, 'total' => $total];
    }

    /** @return array<string, mixed> */
    private function datosDeNivel(int $tenantId, int $rutaId, NivelDeRuta $nivel): array
    {
        return [
            'tenant_id' => $tenantId,
            'ruta_id' => $rutaId,
            'orden' => $nivel->orden,
            'puesto_id' => $nivel->puestoId,
            'nombre' => $nivel->nombre,
            'experiencia_minima_anios' => $nivel->experienciaMinimaAnios,
        ];
    }

    /** @param list<EventoDeRuta> $eventos */
    private function registrar(int $tenantId, int $rutaId, array $eventos): void
    {
        foreach ($eventos as $e) {
            RutaHistorialModel::query()->create([
                'tenant_id' => $tenantId,
                'ruta_id' => $rutaId,
                'inscripcion_id' => $e->inscripcionId,
                'tipo' => $e->tipo,
                'accion' => $e->accion,
                'valor_anterior' => $e->anterior,
                'valor_nuevo' => $e->nuevo,
                'motivo' => $e->motivo,
                'user_id' => $e->usuarioId,
            ]);
        }
    }

    private function aRuta(RutaModel $m): Ruta
    {
        return new Ruta(
            (int) $m->id,
            (int) $m->tenant_id,
            (int) $m->numero,
            (string) $m->nombre,
            TipoDeRuta::from($m->tipo),
            $m->descripcion,
            $m->departamento_id === null ? null : (int) $m->departamento_id,
            EstadoDeRuta::from($m->estado),
            (int) $m->version,
            $m->created_by === null ? null : (int) $m->created_by,
            $m->updated_by === null ? null : (int) $m->updated_by,
            $m->niveles->map(fn (RutaNivelModel $n) => new NivelDeRuta(
                (int) $n->id,
                (int) $n->orden,
                (int) $n->puesto_id,
                (string) $n->nombre,
                $n->experiencia_minima_anios,
            ))->all(),
            $m->created_at?->toDateTimeImmutable(),
            $m->updated_at?->toDateTimeImmutable(),
        );
    }

    private function aInscripcion(RutaInscripcionModel $m): InscripcionARuta
    {
        return new InscripcionARuta(
            (int) $m->id,
            (int) $m->tenant_id,
            (int) $m->ruta_id,
            (int) $m->colaborador_id,
            $m->nivel_actual_id === null ? null : (int) $m->nivel_actual_id,
            $m->nivel_objetivo_id === null ? null : (int) $m->nivel_objetivo_id,
            EstadoDeInscripcion::from($m->estado),
            $m->motivo,
            $m->resuelta_by === null ? null : (int) $m->resuelta_by,
            $m->resuelta_at === null ? null : new DateTimeImmutable((string) $m->resuelta_at),
            $m->created_by === null ? null : (int) $m->created_by,
            $m->created_at?->toDateTimeImmutable(),
        );
    }
}
