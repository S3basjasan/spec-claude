<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Estructura\Domain\Entities\Unidad;
use App\Modules\Estructura\Domain\Exceptions\DesactivacionBloqueada;
use App\Modules\Estructura\Domain\Exceptions\UnidadInvalida;
use App\Modules\Estructura\Domain\Exceptions\UnidadModificada;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoReactivable;
use App\Modules\Estructura\Domain\Repositories\UnidadRepositoryInterface;
use App\Modules\Estructura\Domain\Services\ArbolDeUnidades;
use App\Modules\Estructura\Domain\Services\ComparadorDeCambiosDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\CambioAuditado;
use App\Modules\Estructura\Domain\ValueObjects\DatosUnidad;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPosicion;
use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;
use App\Modules\Estructura\Domain\ValueObjects\ImpactoDeDesactivacion;
use App\Modules\Estructura\Domain\ValueObjects\InstantaneaDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\NodoDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\OpcionesDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\TipoDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\UnidadResumida;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoUnidadModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use Illuminate\Support\Facades\DB;

final class EloquentUnidadRepository implements UnidadRepositoryInterface
{
    private const ENTIDAD = 'unidad';

    private const TABLA = 'unidades_organizacionales';

    public function __construct(private readonly ComparadorDeCambiosDeUnidad $comparador) {}

    // ---------------------------------------------------------------- Lecturas

    public function todas(): array
    {
        return array_values($this->construir()['entidades']);
    }

    public function obtener(int $id): ?Unidad
    {
        return $this->construir()['entidades'][$id] ?? null;
    }

    public function obtenerPorIds(array $ids): array
    {
        $entidades = $this->construir()['entidades'];

        return array_values(array_filter($entidades, fn (Unidad $unidad) => in_array($unidad->id, $ids, true)));
    }

    public function opciones(?int $reubicar): OpcionesDeUnidad
    {
        ['entidades' => $entidades, 'arbol' => $arbol] = $this->construir();
        $tipos = $this->tipos();

        if ($reubicar === null) {
            return new OpcionesDeUnidad($tipos, array_values($entidades));
        }

        $unidad = $entidades[$reubicar] ?? null;
        if ($unidad === null) {
            return new OpcionesDeUnidad($tipos, []);
        }

        $fuera = [$reubicar, ...$arbol->descendientesDe($reubicar)];
        $candidatas = array_values(array_filter(
            $entidades,
            fn (Unidad $candidata) => $candidata->estaActiva()
                && ! in_array($candidata->id, $fuera, true)
                && $candidata->tipo->nivel === $unidad->tipo->nivel - 1,
        ));

        return new OpcionesDeUnidad($tipos, $candidatas);
    }

    public function impactoDeDesactivacion(int $id): ImpactoDeDesactivacion
    {
        ['entidades' => $entidades, 'arbol' => $arbol] = $this->construir();
        $unidad = $entidades[$id] ?? throw new UnidadNoEncontrada;

        $rama = $arbol->rama($id);
        $dependientes = array_values(array_filter($entidades, fn (Unidad $u) => $u->id !== $id && in_array($u->id, $rama, true)));
        $conPosiciones = $this->unidadesConPosiciones($entidades, $rama);

        return new ImpactoDeDesactivacion(
            $unidad->estaActiva() && ! $unidad->esRaiz() && $conPosiciones === [],
            $dependientes,
            $conPosiciones,
        );
    }

    // ------------------------------------------------------------- Escrituras

    public function crear(DatosUnidad $datos, ?int $actorId): Unidad
    {
        if ($datos->unidadPadreId === null) {
            throw UnidadInvalida::padreObligatoria();
        }

        // Ni los tipos ni las unidades se eliminan nunca: comprobar su existencia antes de escribir es seguro y evita
        // un error de clave foránea en lugar de un 422. Aquí se calcula el tipo; se vuelve a comprobar dentro de la
        // transacción contra el estado vigente.
        $padre = UnidadModel::query()->with('tipo')->find($datos->unidadPadreId) ?? throw UnidadInvalida::padreInexistente();
        $tipo = $this->tipoDelNivel((int) $padre->tipo->nivel + 1) ?? throw UnidadInvalida::sinSubunidades();

        $id = DB::transaction(function () use ($datos, $actorId, $padre, $tipo) {
            // El primer enunciado es una escritura: toma el candado de SQLite y las lecturas siguientes ven el último
            // estado confirmado.
            $unidad = UnidadModel::query()->create([
                'nombre' => trim($datos->nombre),
                'tipo_unidad_id' => $tipo->id,
                'unidad_padre_id' => $padre->id,
                'estado' => EstadoUnidad::Activa->value,
                'fecha_creacion' => now()->toDateString(),
                'version' => 1,
                'created_by' => $actorId,
            ]);

            $vigente = UnidadModel::query()->with('tipo')->find($padre->id);
            if ($vigente === null || $vigente->estado !== EstadoUnidad::Activa->value) {
                throw UnidadInvalida::padreInactivo();
            }
            $tipoVigente = $this->tipoDelNivel((int) $vigente->tipo->nivel + 1) ?? throw UnidadInvalida::sinSubunidades();
            if ($tipoVigente->id !== $tipo->id) {
                $unidad->forceFill(['tipo_unidad_id' => $tipoVigente->id])->save();
            }

            $unidad->forceFill(['codigo' => UnidadModel::codigoDeId($unidad->id)])->save();

            $this->registrarCambios($unidad->id, $this->comparador->comparar(null, new InstantaneaDeUnidad(
                trim($datos->nombre),
                $tipoVigente->id,
                $padre->id,
                EstadoUnidad::Activa,
            )), $actorId);

            return $unidad->id;
        });

        return $this->obtener($id) ?? throw new UnidadNoEncontrada;
    }

    public function actualizar(int $id, int $versionEsperada, DatosUnidad $datos, ?int $actorId): Unidad
    {
        $actual = UnidadModel::query()->find($id) ?? throw new UnidadNoEncontrada;
        $nombre = trim($datos->nombre);
        $padreActual = $actual->unidad_padre_id === null ? null : (int) $actual->unidad_padre_id;

        // La raíz sigue sin unidad padre; cualquier otra unidad la conserva obligatoria.
        if ($padreActual === null && $datos->unidadPadreId !== null) {
            throw UnidadInvalida::raizNoReubicable();
        }
        if ($padreActual !== null && $datos->unidadPadreId === null) {
            throw UnidadInvalida::padreObligatoria();
        }
        if ($datos->unidadPadreId !== null && $datos->unidadPadreId !== $padreActual
            && ! UnidadModel::query()->whereKey($datos->unidadPadreId)->exists()) {
            throw UnidadInvalida::padreInexistente();
        }

        // Guardar sin cambios reales no modifica nada: ni la unidad, ni la versión, ni la auditoría.
        if ($nombre === $actual->nombre && $datos->unidadPadreId === $padreActual) {
            return $this->obtener($id) ?? throw new UnidadNoEncontrada;
        }

        DB::transaction(function () use ($id, $versionEsperada, $datos, $nombre, $actorId) {
            // Primer enunciado: sube la versión y toma el candado de escritura. Cero filas = otra edición llegó antes.
            $afectadas = DB::table(self::TABLA)->where('id', $id)->where('version', $versionEsperada)->update([
                'version' => DB::raw('version + 1'),
                'updated_by' => $actorId,
                'updated_at' => now(),
            ]);
            if ($afectadas === 0) {
                throw new UnidadModificada;
            }

            $fresca = UnidadModel::query()->with('tipo')->findOrFail($id);
            $padreVigente = $fresca->unidad_padre_id === null ? null : (int) $fresca->unidad_padre_id;

            // Conservar sin cambios la padre que ya tiene siempre se acepta; elegir otra exige las reglas del árbol.
            if ($datos->unidadPadreId !== null && $datos->unidadPadreId !== $padreVigente) {
                if ($datos->unidadPadreId === $id) {
                    throw UnidadInvalida::padreEsLaMisma();
                }
                if (in_array($datos->unidadPadreId, $this->arbolVigente()->descendientesDe($id), true)) {
                    throw UnidadInvalida::padreEsDependiente();
                }
                $nuevaPadre = UnidadModel::query()->with('tipo')->find($datos->unidadPadreId) ?? throw UnidadInvalida::padreInexistente();
                if ($nuevaPadre->estado !== EstadoUnidad::Activa->value) {
                    throw UnidadInvalida::padreInactivo();
                }
                if ((int) $nuevaPadre->tipo->nivel !== (int) $fresca->tipo->nivel - 1) {
                    throw UnidadInvalida::padreDeOtroTipo();
                }

                // La jerarquía de las posiciones (feature 006): mover la unidad no puede dejar a una posición vigente
                // reportando a otra de una unidad inferior o de otra rama.
                $rotas = $this->posicionesQueRompenLaJerarquia($id, $datos->unidadPadreId);
                if ($rotas > 0) {
                    throw UnidadInvalida::reubicacionRompeJerarquia($rotas);
                }
            }

            $anterior = $this->instantanea($fresca);
            $fresca->forceFill(['nombre' => $nombre, 'unidad_padre_id' => $datos->unidadPadreId])->save();

            $this->registrarCambios($id, $this->comparador->comparar($anterior, $this->instantanea($fresca->refresh())), $actorId);
        });

        return $this->obtener($id) ?? throw new UnidadNoEncontrada;
    }

    public function cambiarEstado(int $id, EstadoUnidad $estado, ?int $actorId): Unidad
    {
        $actual = UnidadModel::query()->find($id) ?? throw new UnidadNoEncontrada;

        if ($estado === EstadoUnidad::Inactiva) {
            if ($actual->unidad_padre_id === null) {
                throw UnidadInvalida::raizNoDesactivable();
            }
            $this->desactivarEnCascada($id, $actorId);
        } else {
            $this->reactivar($id, $this->fecha($actual->fecha_supresion), $actorId);
        }

        return $this->obtener($id) ?? throw new UnidadNoEncontrada;
    }

    /**
     * La unidad y todas sus dependientes activas pasan a Inactiva en una sola transacción, o ninguna. La unidad objetivo
     * se desactiva primero con una sentencia condicional (toma el candado de escritura); la rama se calcula después, ya
     * sin que puedan aparecer dependientes nuevas bajo ella, y si hay posiciones vigentes se revierte todo.
     */
    private function desactivarEnCascada(int $id, ?int $actorId): void
    {
        DB::transaction(function () use ($id, $actorId) {
            $hoy = now()->toDateString();

            $afectadas = DB::table(self::TABLA)->where('id', $id)->where('estado', EstadoUnidad::Activa->value)
                ->update(['estado' => EstadoUnidad::Inactiva->value, 'fecha_supresion' => $hoy]);
            if ($afectadas === 0) {
                return; // ya estaba inactiva: sin cambios ni historial
            }

            ['entidades' => $entidades, 'arbol' => $arbol] = $this->construir();
            $rama = $arbol->rama($id);

            $bloqueos = $this->unidadesConPosiciones($entidades, $rama);
            if ($bloqueos !== []) {
                throw new DesactivacionBloqueada($bloqueos);
            }

            $dependientes = array_values(array_diff($rama, [$id]));
            if ($dependientes !== []) {
                DB::table(self::TABLA)->whereIn('id', $dependientes)->where('estado', EstadoUnidad::Activa->value)
                    ->update(['estado' => EstadoUnidad::Inactiva->value, 'fecha_supresion' => $hoy]);
            }

            foreach ($rama as $desactivada) {
                $unidad = $entidades[$desactivada];
                $anterior = new InstantaneaDeUnidad($unidad->nombre, $unidad->tipo->id, $unidad->padre?->id, EstadoUnidad::Activa);
                $nueva = new InstantaneaDeUnidad($unidad->nombre, $unidad->tipo->id, $unidad->padre?->id, EstadoUnidad::Inactiva, $hoy);
                $this->registrarCambios($desactivada, $this->comparador->comparar($anterior, $nueva), $actorId);
            }
        });
    }

    private function reactivar(int $id, ?string $fechaAnterior, ?int $actorId): void
    {
        DB::transaction(function () use ($id, $fechaAnterior, $actorId) {
            // Una sola sentencia condicional: solo si la unidad padre está activa. No reactiva las dependientes.
            $afectadas = DB::table(self::TABLA)
                ->where('id', $id)
                ->where('estado', EstadoUnidad::Inactiva->value)
                ->where(function ($padre) {
                    $padre->whereNull('unidad_padre_id')->orWhereExists(function ($activo) {
                        $activo->selectRaw('1')->from(self::TABLA.' as padre')
                            ->whereColumn('padre.id', self::TABLA.'.unidad_padre_id')
                            ->where('padre.estado', EstadoUnidad::Activa->value);
                    });
                })
                ->update(['estado' => EstadoUnidad::Activa->value, 'fecha_supresion' => null]);

            if ($afectadas === 0) {
                if (DB::table(self::TABLA)->where('id', $id)->value('estado') === EstadoUnidad::Activa->value) {
                    return; // ya estaba activa: sin cambios ni historial
                }
                throw new UnidadNoReactivable;
            }

            $unidad = UnidadModel::query()->findOrFail($id);
            $base = $this->instantanea($unidad);
            $anterior = new InstantaneaDeUnidad($base->nombre, $base->tipoUnidadId, $base->unidadPadreId, EstadoUnidad::Inactiva, $fechaAnterior);
            $this->registrarCambios($id, $this->comparador->comparar($anterior, $base), $actorId);
        });
    }

    // ------------------------------------------------------------------ Apoyo

    /**
     * Árbol completo con contadores, niveles, indicadores de la cadena y datos de detalle de cada unidad.
     *
     * @return array{entidades: array<int, Unidad>, arbol: ArbolDeUnidades}
     */
    private function construir(): array
    {
        $modelos = UnidadModel::query()->with('tipo')->get()->keyBy('id');
        $ultimoNivel = (int) TipoUnidadModel::query()->max('nivel');

        $vigentesPorUnidad = DB::table('posiciones')
            ->whereNotNull('unidad_organizacional_id')
            ->whereIn('estado', $this->estadosVigentes())
            ->groupBy('unidad_organizacional_id')
            ->selectRaw('unidad_organizacional_id, count(*) as total')
            ->pluck('total', 'unidad_organizacional_id');

        $nodos = $modelos->map(fn (UnidadModel $unidad) => new NodoDeUnidad(
            $unidad->id,
            (string) $unidad->codigo,
            $unidad->nombre,
            new TipoDeUnidad($unidad->tipo->id, $unidad->tipo->nombre, (int) $unidad->tipo->nivel),
            $unidad->unidad_padre_id === null ? null : (int) $unidad->unidad_padre_id,
            EstadoUnidad::from($unidad->estado),
            0,
            (int) ($vigentesPorUnidad[$unidad->id] ?? 0),
        ))->all();

        $arbol = new ArbolDeUnidades($nodos);

        $entidades = [];
        foreach ($arbol->ordenados() as $id) {
            $nodo = $nodos[$id];
            $modelo = $modelos[$id];
            $padre = $nodo->unidadPadreId === null ? null : ($nodos[$nodo->unidadPadreId] ?? null);
            $editada = (int) $modelo->version > 1;

            $entidades[$id] = new Unidad(
                id: $id,
                codigo: $nodo->codigo,
                nombre: $nodo->nombre,
                tipo: $nodo->tipo,
                padre: $padre === null ? null : new UnidadResumida($padre->id, $padre->codigo, $padre->nombre, $padre->estado),
                nivel: $arbol->nivelDe($id),
                estado: $nodo->estado,
                dependientesActivas: count($arbol->rama($id)) - 1,
                posicionesVigentes: $nodo->posicionesVigentes,
                version: (int) $modelo->version,
                fechaCreacion: $this->fecha($modelo->fecha_creacion),
                fechaSupresion: $this->fecha($modelo->fecha_supresion),
                dependientes: array_map(
                    fn (int $hija) => new UnidadResumida($hija, $nodos[$hija]->codigo, $nodos[$hija]->nombre, $nodos[$hija]->estado),
                    $arbol->hijasDe($id),
                ),
                creadoPor: $modelo->created_by === null ? null : (int) $modelo->created_by,
                creadoEn: $modelo->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
                modificadoPor: $editada && $modelo->updated_by !== null ? (int) $modelo->updated_by : null,
                modificadoEn: $editada ? $modelo->updated_at?->utc()->format('Y-m-d\TH:i:s\Z') : null,
                tieneDependientes: $arbol->hijasDe($id) !== [],
                admiteHijas: $nodo->tipo->nivel < $ultimoNivel,
                sigueCadena: $padre === null || $nodo->tipo->nivel === $padre->tipo->nivel + 1,
            );
        }

        return ['entidades' => $entidades, 'arbol' => $arbol];
    }

    /**
     * Cantidad de posiciones vigentes que, con la unidad bajo su nueva unidad padre, dejarían de tener a su supervisora en
     * la misma unidad o en una unidad superior. Se evalúa sobre el árbol resultante de la reubicación.
     */
    private function posicionesQueRompenLaJerarquia(int $unidadId, int $nuevoPadreId): int
    {
        /** @var array<int, int|null> $padres */
        $padres = DB::table(self::TABLA)->pluck('unidad_padre_id', 'id')
            ->map(fn ($padre) => $padre === null ? null : (int) $padre)
            ->all();
        $padres[$unidadId] = $nuevoPadreId;

        $superiores = function (int $unidad) use ($padres): array {
            $resultado = [];
            $visitadas = [$unidad => true];
            for ($actual = $padres[$unidad] ?? null; $actual !== null && ! isset($visitadas[$actual]); $actual = $padres[$actual] ?? null) {
                $visitadas[$actual] = true;
                $resultado[] = $actual;
            }

            return $resultado;
        };

        return DB::table('posiciones as p')
            ->join('posiciones as s', 's.id', '=', 'p.posicion_jefe_id')
            ->where('p.estado', '<>', EstadoPosicion::Suprimida->value)
            ->where('s.estado', '<>', EstadoPosicion::Suprimida->value)
            ->get(['p.unidad_organizacional_id as unidad', 's.unidad_organizacional_id as unidad_supervisora'])
            ->filter(function (object $fila) use ($superiores) {
                $unidad = (int) $fila->unidad;
                $supervisora = (int) $fila->unidad_supervisora;

                return $supervisora !== $unidad && ! in_array($supervisora, $superiores($unidad), true);
            })
            ->count();
    }

    /** @return array<int, TipoDeUnidad> */
    private function tipos(): array
    {
        return TipoUnidadModel::query()->orderBy('nivel')->get()
            ->map(fn (TipoUnidadModel $tipo) => new TipoDeUnidad($tipo->id, $tipo->nombre, (int) $tipo->nivel))
            ->all();
    }

    private function tipoDelNivel(int $nivel): ?TipoUnidadModel
    {
        return TipoUnidadModel::query()->where('nivel', $nivel)->first();
    }

    private function arbolVigente(): ArbolDeUnidades
    {
        return $this->construir()['arbol'];
    }

    /**
     * Unidades de la rama con posiciones Ocupadas o Vacantes y su cantidad.
     *
     * @param  array<int, Unidad>  $entidades
     * @param  array<int, int>  $rama
     * @return array<int, array{id: int, codigo: string, nombre: string, cantidad: int}>
     */
    private function unidadesConPosiciones(array $entidades, array $rama): array
    {
        $resultado = [];
        foreach ($entidades as $unidad) {
            if (in_array($unidad->id, $rama, true) && $unidad->posicionesVigentes > 0) {
                $resultado[] = [
                    'id' => $unidad->id,
                    'codigo' => $unidad->codigo,
                    'nombre' => $unidad->nombre,
                    'cantidad' => $unidad->posicionesVigentes,
                ];
            }
        }

        return $resultado;
    }

    private function instantanea(UnidadModel $unidad): InstantaneaDeUnidad
    {
        return new InstantaneaDeUnidad(
            $unidad->nombre,
            (int) $unidad->tipo_unidad_id,
            $unidad->unidad_padre_id === null ? null : (int) $unidad->unidad_padre_id,
            EstadoUnidad::from($unidad->estado),
            $this->fecha($unidad->fecha_supresion),
        );
    }

    private function fecha(mixed $valor): ?string
    {
        return $valor === null ? null : substr((string) $valor, 0, 10);
    }

    /**
     * @param  array<int, CambioAuditado>  $cambios
     */
    private function registrarCambios(int $unidadId, array $cambios, ?int $actorId): void
    {
        foreach ($cambios as $cambio) {
            AuditoriaCambioModel::query()->create([
                'entidad' => self::ENTIDAD,
                'entidad_id' => $unidadId,
                'campo' => $cambio->campo,
                'valor_anterior' => $cambio->valorAnterior,
                'valor_nuevo' => $cambio->valorNuevo,
                'user_id' => $actorId,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function estadosVigentes(): array
    {
        return array_map(
            fn (EstadoPosicion $estado) => $estado->value,
            array_values(array_filter(EstadoPosicion::cases(), fn (EstadoPosicion $e) => $e->enUso())),
        );
    }
}
