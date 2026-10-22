<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Estructura\Domain\Entities\Competencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\Exceptions\EscalaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\EscalaSinCambio;
use App\Modules\Estructura\Domain\Exceptions\EstadoSinCambio;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\Services\ReglasDeCategorias;
use App\Modules\Estructura\Domain\ValueObjects\EscalaDeGrados;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\GradoDeCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\GradoLetra;
use App\Modules\Estructura\Domain\ValueObjects\PaginaCompetencias;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CategoriaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaEscalaHistorialModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaEstadoHistorialModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\ComportamientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PreguntaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoGradoValorModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentCompetenciaRepository implements CompetenciaRepositoryInterface
{
    /** @var array<int, EscalaDeGrados>|null */
    private ?array $escalas = null;

    public function listar(?string $q, ?TipoCompetencia $tipo, ?EstadoCompetencia $estado, int $pagina, int $porPagina): PaginaCompetencias
    {
        $consulta = CompetenciaModel::query();

        if ($q !== null && $q !== '') {
            $patron = '%'.addcslashes(CompetenciaModel::claveDeNombre($q), '%_\\').'%';
            $consulta->whereRaw('nombre_clave like ? escape ?', [$patron, '\\']);
        }

        if ($tipo !== null) {
            $consulta->where('tipo', $tipo->value);
        }

        if ($estado !== null) {
            $consulta->where('estado', $estado->value);
        }

        $total = (clone $consulta)->count();
        $modelos = $this->conContenido($consulta)->orderBy('codigo')
            ->skip(($pagina - 1) * $porPagina)
            ->take($porPagina)
            ->get();

        return new PaginaCompetencias($modelos->map(fn (CompetenciaModel $m) => $this->aEntidad($m))->all(), $total);
    }

    public function obtener(int $id): ?Competencia
    {
        $modelo = $this->conContenido(CompetenciaModel::query())->find($id);

        return $modelo === null ? null : $this->aEntidad($modelo);
    }

    public function obtenerPorIds(array $ids): array
    {
        return $this->conContenido(CompetenciaModel::query())->whereIn('id', $ids)->orderBy('nombre')->get()
            ->map(fn (CompetenciaModel $m) => $this->aEntidad($m))->all();
    }

    public function obtenerActivas(): array
    {
        return $this->conContenido(CompetenciaModel::query())->where('estado', EstadoCompetencia::Activo->value)->orderBy('nombre')->get()
            ->map(fn (CompetenciaModel $m) => $this->aEntidad($m))->all();
    }

    public function obtenerTodas(): array
    {
        return $this->conContenido(CompetenciaModel::query())->orderBy('nombre')->get()
            ->map(fn (CompetenciaModel $m) => $this->aEntidad($m))->all();
    }

    public function crear(
        string $nombre,
        string $descripcion,
        TipoCompetencia $tipo,
        int $escalaId,
        ?array $categorias,
        array $grados,
        array $comportamientos,
        array $preguntas,
        ?int $actorId,
    ): Competencia {
        try {
            $id = DB::transaction(function () use ($nombre, $descripcion, $tipo, $escalaId, $categorias, $grados, $comportamientos, $preguntas, $actorId) {
                $valores = $this->valoresDeEscala($escalaId);

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

                foreach ($valores as $indice => $valor) {
                    $grado = CompetenciaGradoModel::query()->create([
                        'competencia_id' => $modelo->id,
                        'tipo_grado_valor_id' => $valor->id,
                        'descripcion' => $grados[$indice],
                        'created_by' => $actorId,
                    ]);
                    $this->guardarComportamientosDeGrado($grado->id, $comportamientos[$indice], $actorId);
                }

                $this->guardarPreguntas($modelo->id, $preguntas, $actorId);

                if ($categorias !== null) {
                    $this->guardarCategorias($modelo->id, $categorias, $actorId);
                }

                return $modelo->id;
            });
        } catch (UniqueConstraintViolationException) {
            throw new NombreDuplicado;
        }

        return $this->obtener($id) ?? throw new CompetenciaNoEncontrada;
    }

    public function actualizar(
        int $id,
        int $versionEsperada,
        string $nombre,
        string $descripcion,
        TipoCompetencia $tipo,
        int $escalaId,
        ?array $categorias,
        array $grados,
        ?int $actorId,
    ): Competencia {
        try {
            DB::transaction(function () use ($id, $versionEsperada, $nombre, $descripcion, $tipo, $escalaId, $categorias, $grados, $actorId) {
                $valores = $this->valoresDeEscala($escalaId);

                $this->subirVersion($id, $versionEsperada, [
                    'nombre' => $nombre,
                    'nombre_clave' => CompetenciaModel::claveDeNombre($nombre),
                    'descripcion' => $descripcion,
                    'tipo' => $tipo->value,
                ], $actorId);

                $filas = $this->gradosOrdenados($id);
                $tipoActual = $filas->first()?->valor->tipo_grado_id;

                if ($tipoActual !== null && (int) $tipoActual !== $escalaId) {
                    $this->registrarCambioDeEscala($id, (int) $tipoActual, $escalaId, $actorId);
                }

                foreach ($valores as $indice => $valor) {
                    $fila = $filas->get($indice);

                    if ($fila === null) {
                        CompetenciaGradoModel::query()->create([
                            'competencia_id' => $id,
                            'tipo_grado_valor_id' => $valor->id,
                            'descripcion' => $grados[$indice],
                            'created_by' => $actorId,
                        ]);

                        continue;
                    }

                    $fila->forceFill([
                        'tipo_grado_valor_id' => $valor->id,
                        'descripcion' => $grados[$indice],
                        'updated_by' => $actorId,
                    ])->save();
                }

                if ($categorias !== null) {
                    $this->guardarCategorias($id, $categorias, $actorId);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw new NombreDuplicado;
        }

        return $this->obtener($id) ?? throw new CompetenciaNoEncontrada;
    }

    public function actualizarComportamientos(int $id, int $versionEsperada, array $comportamientos, ?int $actorId): Competencia
    {
        DB::transaction(function () use ($id, $versionEsperada, $comportamientos, $actorId) {
            $this->subirVersion($id, $versionEsperada, [], $actorId);

            foreach ($this->gradosOrdenados($id) as $indice => $grado) {
                $this->guardarComportamientosDeGrado($grado->id, $comportamientos[$indice], $actorId);
            }
        });

        return $this->obtener($id) ?? throw new CompetenciaNoEncontrada;
    }

    public function actualizarPreguntas(int $id, int $versionEsperada, array $preguntas, ?int $actorId): Competencia
    {
        DB::transaction(function () use ($id, $versionEsperada, $preguntas, $actorId) {
            $this->subirVersion($id, $versionEsperada, [], $actorId);
            $this->guardarPreguntas($id, $preguntas, $actorId);
        });

        return $this->obtener($id) ?? throw new CompetenciaNoEncontrada;
    }

    public function aplicarEscala(int $id, int $escalaId, ?int $actorId): Competencia
    {
        DB::transaction(function () use ($id, $escalaId, $actorId) {
            if (! CompetenciaModel::query()->whereKey($id)->exists()) {
                throw new CompetenciaNoEncontrada;
            }

            $valores = $this->valoresDeEscala($escalaId);
            $filas = $this->gradosOrdenados($id);
            $tipoActual = $filas->first()?->valor->tipo_grado_id;

            if ($tipoActual !== null && (int) $tipoActual === $escalaId) {
                throw new EscalaSinCambio;
            }

            if ($tipoActual !== null) {
                $this->registrarCambioDeEscala($id, (int) $tipoActual, $escalaId, $actorId);
            }

            foreach ($valores as $indice => $valor) {
                $filas->get($indice)?->forceFill(['tipo_grado_valor_id' => $valor->id, 'updated_by' => $actorId])->save();
            }

            $this->subirVersion($id, null, [], $actorId);
        });

        return $this->obtener($id) ?? throw new CompetenciaNoEncontrada;
    }

    public function cambiarEstado(int $id, EstadoCompetencia $nuevo, ?int $actorId): Competencia
    {
        DB::transaction(function () use ($id, $nuevo, $actorId) {
            // Actualización condicional: dos cambios simultáneos al mismo estado dejan un solo registro de historial.
            $afectadas = DB::table('competencias')
                ->where('id', $id)
                ->where('estado', '<>', $nuevo->value)
                ->update([
                    'estado' => $nuevo->value,
                    'version' => DB::raw('version + 1'),
                    'updated_by' => $actorId,
                    'updated_at' => now(),
                ]);

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

    // ------------------------------------------------------------- Escritura

    /**
     * Sube la versión con una sola sentencia condicional (atómica): si se espera una versión y no coincide,
     * hay conflicto; las acciones puntuales (estado y escala) no esperan versión.
     *
     * @param  array<string, mixed>  $campos
     */
    private function subirVersion(int $id, ?int $versionEsperada, array $campos, ?int $actorId): void
    {
        $consulta = CompetenciaModel::query()->where('id', $id);

        if ($versionEsperada !== null) {
            $consulta->where('version', $versionEsperada);
        }

        $afectadas = $consulta->update(array_merge($campos, [
            'version' => DB::raw('version + 1'),
            'updated_by' => $actorId,
            'updated_at' => now(),
        ]));

        if ($afectadas === 0) {
            throw CompetenciaModel::query()->whereKey($id)->exists()
                ? new ConflictoDeVersion
                : new CompetenciaNoEncontrada;
        }
    }

    /**
     * @return Collection<int, TipoGradoValorModel>
     *
     * @throws EscalaNoEncontrada
     */
    private function valoresDeEscala(int $escalaId): Collection
    {
        $valores = TipoGradoValorModel::query()->where('tipo_grado_id', $escalaId)->orderBy('orden')->get()->values();

        if ($valores->count() !== 4) {
            throw new EscalaNoEncontrada;
        }

        return $valores;
    }

    /**
     * @return Collection<int, CompetenciaGradoModel>
     */
    private function gradosOrdenados(int $competenciaId): Collection
    {
        return CompetenciaGradoModel::query()
            ->with('valor.tipo')
            ->where('competencia_id', $competenciaId)
            ->get()
            ->sortBy(fn (CompetenciaGradoModel $g) => $g->valor->orden)
            ->values();
    }

    private function registrarCambioDeEscala(int $competenciaId, int $tipoAnteriorId, int $tipoNuevoId, ?int $actorId): void
    {
        CompetenciaEscalaHistorialModel::query()->create([
            'competencia_id' => $competenciaId,
            'escala_anterior' => (string) TipoGradoModel::query()->whereKey($tipoAnteriorId)->value('nombre'),
            'escala_nueva' => (string) TipoGradoModel::query()->whereKey($tipoNuevoId)->value('nombre'),
            'user_id' => $actorId,
        ]);
    }

    /**
     * @param  array<int, string>  $textos  Hasta cuatro textos; el orden es la posición (a partir de 1).
     */
    private function guardarComportamientosDeGrado(int $gradoId, array $textos, ?int $actorId): void
    {
        foreach (array_values($textos) as $indice => $texto) {
            $fila = ComportamientoModel::query()->firstOrNew(['competencia_grado_id' => $gradoId, 'orden' => $indice + 1]);

            if (! $fila->exists) {
                $fila->created_by = $actorId;
            }

            $fila->fill(['descripcion' => $texto, 'updated_by' => $actorId])->save();
        }
    }

    /**
     * @param  array<int, string>  $textos
     */
    private function guardarPreguntas(int $competenciaId, array $textos, ?int $actorId): void
    {
        foreach (array_values($textos) as $indice => $texto) {
            $fila = PreguntaModel::query()->firstOrNew(['competencia_id' => $competenciaId, 'orden' => $indice + 1]);

            if (! $fila->exists) {
                $fila->created_by = $actorId;
            }

            $fila->fill(['pregunta' => $texto, 'updated_by' => $actorId])->save();
        }
    }

    /**
     * Cuatro nombres propios de la escala Categorías. Se reemplazan en dos pasos para que intercambiar nombres
     * entre posiciones no choque con la unicidad por competencia.
     *
     * @param  array<int, string>  $nombres
     */
    private function guardarCategorias(int $competenciaId, array $nombres, ?int $actorId): void
    {
        $nombres = array_values($nombres);

        CategoriaModel::query()->where('competencia_id', $competenciaId)
            ->update(['nombre_clave' => DB::raw("'~' || orden")]);

        foreach ($nombres as $indice => $nombre) {
            $fila = CategoriaModel::query()->firstOrNew(['competencia_id' => $competenciaId, 'orden' => $indice + 1]);

            if (! $fila->exists) {
                $fila->created_by = $actorId;
            }

            $fila->fill([
                'nombre' => trim($nombre),
                'nombre_clave' => ReglasDeCategorias::clave($nombre),
                'updated_by' => $actorId,
            ])->save();
        }
    }

    // --------------------------------------------------------------- Lectura

    private function conContenido(Builder $consulta): Builder
    {
        return $consulta->with(['grados.valor', 'grados.comportamientos', 'preguntas', 'categorias']);
    }

    /**
     * @return array<int, EscalaDeGrados>
     */
    private function escalas(): array
    {
        if ($this->escalas === null) {
            $this->escalas = [];

            foreach (TipoGradoModel::query()->with('valores')->get() as $tipo) {
                $this->escalas[$tipo->id] = new EscalaDeGrados(
                    $tipo->id,
                    $tipo->nombre,
                    (bool) $tipo->activo,
                    $tipo->valores->mapWithKeys(fn (TipoGradoValorModel $v) => [(int) $v->orden => $v->valor])->all(),
                );
            }
        }

        return $this->escalas;
    }

    private function aEntidad(CompetenciaModel $modelo): Competencia
    {
        $filas = $modelo->grados->sortBy(fn (CompetenciaGradoModel $g) => $g->valor->orden)->values();
        $escala = $filas->isEmpty() ? null : ($this->escalas()[(int) $filas->first()->valor->tipo_grado_id] ?? null);

        $categorias = $modelo->categorias->isEmpty()
            ? null
            : $modelo->categorias->mapWithKeys(fn (CategoriaModel $c) => [(int) $c->orden => $c->nombre])->all();

        $detalle = [];
        $mapa = [];
        foreach ($filas as $fila) {
            $orden = (int) $fila->valor->orden;
            $letra = GradoLetra::desdeOrden($orden);
            $mapa[$letra] = $fila->descripcion;
            $detalle[] = new GradoDeCompetencia(
                $fila->id,
                $orden,
                $letra,
                $escala?->etiqueta($orden, $categorias) ?? 'Grado '.$letra,
                $fila->descripcion,
                $fila->comportamientos->pluck('descripcion')->values()->all(),
            );
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
            grados: $mapa,
            escala: $escala,
            gradosDetalle: $detalle,
            preguntas: $modelo->preguntas->pluck('pregunta')->values()->all(),
            categorias: $categorias,
            creadaEn: $modelo->created_at?->toIso8601String(),
            modificadaEn: $modelo->updated_at?->toIso8601String(),
        );
    }
}
