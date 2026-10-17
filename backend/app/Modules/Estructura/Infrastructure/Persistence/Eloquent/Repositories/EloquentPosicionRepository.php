<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Estructura\Domain\Entities\Posicion;
use App\Modules\Estructura\Domain\Exceptions\PosicionInvalida;
use App\Modules\Estructura\Domain\Exceptions\PosicionModificada;
use App\Modules\Estructura\Domain\Exceptions\PosicionNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\PosicionNoSuprimible;
use App\Modules\Estructura\Domain\Repositories\PosicionRepositoryInterface;
use App\Modules\Estructura\Domain\Services\ArbolDeSupervision;
use App\Modules\Estructura\Domain\Services\ArbolDeUnidades;
use App\Modules\Estructura\Domain\Services\ComparadorDeCambiosDePosicion;
use App\Modules\Estructura\Domain\Services\JerarquiaDeUnidades;
use App\Modules\Estructura\Domain\ValueObjects\CambioAuditado;
use App\Modules\Estructura\Domain\ValueObjects\DatosPosicion;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPosicion;
use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;
use App\Modules\Estructura\Domain\ValueObjects\FiltroDePosiciones;
use App\Modules\Estructura\Domain\ValueObjects\InstantaneaDePosicion;
use App\Modules\Estructura\Domain\ValueObjects\Modalidad;
use App\Modules\Estructura\Domain\ValueObjects\NodoDePosicion;
use App\Modules\Estructura\Domain\ValueObjects\NodoDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\OpcionesDePosicion;
use App\Modules\Estructura\Domain\ValueObjects\PaginaDePosiciones;
use App\Modules\Estructura\Domain\ValueObjects\TipoDeUnidad;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentPosicionRepository implements PosicionRepositoryInterface
{
    private const ENTIDAD = 'posicion';

    private const TABLA = 'posiciones';

    public function __construct(private readonly ComparadorDeCambiosDePosicion $comparador) {}

    // ---------------------------------------------------------------- Lecturas

    public function listar(FiltroDePosiciones $filtro): PaginaDePosiciones
    {
        $consulta = $this->consulta();
        $this->aplicarFiltro($consulta, $filtro);

        $total = (clone $consulta)->count();
        $filas = $consulta->orderBy('p.id')->forPage($filtro->pagina, $filtro->porPagina)->get();

        return new PaginaDePosiciones($this->construir($filas->all()), $total, $filtro->pagina, $filtro->porPagina);
    }

    public function obtener(int $id): ?Posicion
    {
        $fila = $this->consulta()->where('p.id', $id)->first();
        if ($fila === null) {
            return null;
        }

        return $this->construir([$fila], conSubordinadas: true)[0] ?? null;
    }

    public function obtenerPorIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->construir($this->consulta()->whereIn('p.id', $ids)->orderBy('p.id')->get()->all());
    }

    public function opciones(?int $excluir): OpcionesDePosicion
    {
        $puestos = DB::table('puestos')->where('estado', 'Activo')->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'descripcion'])
            ->map(fn (object $p) => [
                'id' => (int) $p->id, 'codigo' => (string) $p->codigo, 'nombre' => (string) $p->nombre, 'descripcion' => (string) $p->descripcion,
            ])->all();

        $hayMaxima = DB::table(self::TABLA)
            ->whereNull('posicion_jefe_id')
            ->where('estado', '<>', EstadoPosicion::Suprimida->value)
            ->when($excluir !== null, fn (Builder $q) => $q->where('id', '<>', $excluir))
            ->exists();

        // La raíz solo la ocupa la máxima autoridad: queda deshabilitada si ya tiene otra posición vigente, o si la
        // posición que se edita no es la máxima autoridad.
        $excluida = $excluir === null ? null : DB::table(self::TABLA)->where('id', $excluir)->first(['unidad_organizacional_id', 'posicion_jefe_id']);
        $raizNoDisponible = $hayMaxima || ($excluida !== null && $excluida->posicion_jefe_id !== null);

        $unidades = [];
        ['nodos' => $nodos, 'arbol' => $arbol] = $this->arbolDeUnidades();
        $jerarquia = new JerarquiaDeUnidades($arbol);
        $raiz = $jerarquia->raiz();
        foreach ($arbol->ordenados() as $id) {
            $nodo = $nodos[$id];
            $motivo = $nodo->estado !== EstadoUnidad::Activa ? 'inactiva' : ($id === $raiz && $raizNoDisponible ? 'raiz_ocupada' : null);
            $unidades[] = [
                'id' => $nodo->id,
                'codigo' => $nodo->codigo,
                'nombre' => $nodo->nombre,
                'tipo' => ['id' => $nodo->tipo->id, 'nombre' => $nodo->tipo->nombre, 'nivel' => $nodo->tipo->nivel],
                'nivel_arbol' => $arbol->nivelDe($id),
                'estado' => $nodo->estado->value,
                'unidad_padre_id' => $nodo->unidadPadreId,
                'superiores' => $jerarquia->superioresDe($id),
                'habilitada' => $motivo === null,
                'motivo_deshabilitada' => $motivo,
            ];
        }

        $fuera = [];
        $unidadesMinimas = [];
        if ($excluir !== null) {
            $fuera = [$excluir, ...(new ArbolDeSupervision($this->arbolDeSupervision()))->subordinadasDe($excluir)];
            $unidadesMinimas = array_values(array_unique(DB::table(self::TABLA)
                ->where('posicion_jefe_id', $excluir)
                ->where('estado', '<>', EstadoPosicion::Suprimida->value)
                ->pluck('unidad_organizacional_id')
                ->map(fn ($unidad) => (int) $unidad)
                ->all()));
        }

        $candidatas = DB::table('posiciones as p')
            ->join('puestos as pu', 'pu.id', '=', 'p.puesto_id')
            ->join('unidades_organizacionales as u', 'u.id', '=', 'p.unidad_organizacional_id')
            ->where('p.estado', '<>', EstadoPosicion::Suprimida->value)
            ->when($fuera !== [], fn (Builder $q) => $q->whereNotIn('p.id', $fuera))
            ->orderBy('p.id')
            ->get(['p.id', 'p.codigo', 'pu.nombre as puesto', 'p.user_id', 'p.unidad_organizacional_id', 'u.nombre as unidad'])
            ->map(fn (object $p) => [
                'id' => (int) $p->id,
                'codigo' => (string) ($p->codigo ?? PosicionModel::codigoDeId((int) $p->id)),
                'puesto' => (string) $p->puesto,
                'ocupante_id' => $p->user_id === null ? null : (int) $p->user_id,
                'unidad_organizacional_id' => (int) $p->unidad_organizacional_id,
                'unidad' => (string) $p->unidad,
            ])->all();

        return new OpcionesDePosicion($puestos, $unidades, $candidatas, $hayMaxima, $unidadesMinimas);
    }

    public function arbolDeSupervision(): array
    {
        return DB::table(self::TABLA)->where('estado', '<>', EstadoPosicion::Suprimida->value)->orderBy('id')
            ->get(['id', 'codigo', 'posicion_jefe_id', 'estado'])
            ->map(fn (object $p) => new NodoDePosicion(
                (int) $p->id,
                (string) ($p->codigo ?? PosicionModel::codigoDeId((int) $p->id)),
                $p->posicion_jefe_id === null ? null : (int) $p->posicion_jefe_id,
                EstadoPosicion::from($p->estado),
            ))->all();
    }

    // ------------------------------------------------------------- Escrituras

    public function crear(DatosPosicion $datos, ?int $actorId): Posicion
    {
        if ($datos->puestoId === null) {
            throw PosicionInvalida::puestoObligatorio();
        }

        // Ni los puestos, ni las unidades ni las posiciones se eliminan nunca: comprobar su existencia antes de escribir
        // es seguro y evita un error de clave foránea en lugar de un 422. El estado se vuelve a comprobar dentro de la
        // transacción contra el estado vigente.
        if (! PuestoModel::query()->whereKey($datos->puestoId)->exists()) {
            throw PosicionInvalida::puestoInexistente();
        }
        if (! UnidadModel::query()->whereKey($datos->unidadId)->exists()) {
            throw PosicionInvalida::unidadInexistente();
        }
        if ($datos->supervisorId !== null && ! PosicionModel::query()->whereKey($datos->supervisorId)->exists()) {
            throw PosicionInvalida::supervisorInexistente();
        }

        // La raíz es la unidad sin padre y no cambia: «sin supervisor ⇔ raíz» se puede comprobar antes de escribir.
        $esRaiz = $this->jerarquia()->esRaiz($datos->unidadId);
        if ($esRaiz && $datos->supervisorId !== null) {
            throw PosicionInvalida::supervisorEnRaiz();
        }
        if (! $esRaiz && $datos->supervisorId === null) {
            throw PosicionInvalida::supervisorObligatorio();
        }

        try {
            $id = DB::transaction(function () use ($datos, $actorId, $esRaiz) {
                // El primer enunciado es una escritura: toma el candado de SQLite y las lecturas siguientes ven el último
                // estado confirmado. El índice parcial rechaza una segunda máxima autoridad vigente.
                $posicion = PosicionModel::query()->create([
                    'puesto_id' => $datos->puestoId,
                    'unidad_organizacional_id' => $datos->unidadId,
                    'posicion_jefe_id' => $datos->supervisorId,
                    'estado' => EstadoPosicion::Vacante->value,
                    'modalidad' => $datos->modalidad->value,
                    'version' => 1,
                    'created_by' => $actorId,
                ]);

                if (PuestoModel::query()->whereKey($datos->puestoId)->value('estado') !== 'Activo') {
                    throw PosicionInvalida::puestoInactivo();
                }
                if (UnidadModel::query()->whereKey($datos->unidadId)->value('estado') !== EstadoUnidad::Activa->value) {
                    throw PosicionInvalida::unidadInactiva();
                }
                if ($datos->supervisorId !== null
                    && PosicionModel::query()->whereKey($datos->supervisorId)->value('estado') === EstadoPosicion::Suprimida->value) {
                    throw PosicionInvalida::supervisorSuprimido();
                }

                // La unidad raíz tiene una sola posición vigente; el resto cumple la jerarquía de unidades.
                if ($esRaiz) {
                    $otras = PosicionModel::query()->where('unidad_organizacional_id', $datos->unidadId)
                        ->where('estado', '<>', EstadoPosicion::Suprimida->value)->where('id', '<>', $posicion->id)->exists();
                    if ($otras) {
                        throw PosicionInvalida::raizOcupada();
                    }
                } else {
                    $this->validarJerarquia($datos->unidadId, (int) $datos->supervisorId);
                }

                $posicion->forceFill(['codigo' => PosicionModel::codigoDeId($posicion->id)])->save();

                $this->registrarCambios($posicion->id, $this->comparador->comparar(null, new InstantaneaDePosicion(
                    $datos->puestoId,
                    $datos->unidadId,
                    $datos->supervisorId,
                    $datos->modalidad,
                    EstadoPosicion::Vacante,
                )), $actorId);

                return $posicion->id;
            });
        } catch (UniqueConstraintViolationException) {
            throw PosicionInvalida::maximaAutoridadYaExiste();
        }

        return $this->obtener($id) ?? throw new PosicionNoEncontrada;
    }

    public function actualizar(int $id, int $versionEsperada, DatosPosicion $datos, ?int $actorId): Posicion
    {
        $actual = PosicionModel::query()->find($id) ?? throw new PosicionNoEncontrada;
        if ($actual->estado === EstadoPosicion::Suprimida->value) {
            throw PosicionInvalida::suprimida();
        }

        if ($datos->unidadId !== (int) $actual->unidad_organizacional_id
            && ! UnidadModel::query()->whereKey($datos->unidadId)->exists()) {
            throw PosicionInvalida::unidadInexistente();
        }
        $supervisorActual = $actual->posicion_jefe_id === null ? null : (int) $actual->posicion_jefe_id;
        if ($datos->supervisorId !== null && $datos->supervisorId !== $supervisorActual
            && ! PosicionModel::query()->whereKey($datos->supervisorId)->exists()) {
            throw PosicionInvalida::supervisorInexistente();
        }

        // Guardar sin cambios reales no modifica nada: ni la posición, ni la versión, ni el historial.
        if ($datos->unidadId === (int) $actual->unidad_organizacional_id
            && $datos->supervisorId === $supervisorActual
            && $datos->modalidad->value === $actual->modalidad) {
            return $this->obtener($id) ?? throw new PosicionNoEncontrada;
        }

        try {
            DB::transaction(function () use ($id, $versionEsperada, $datos, $actorId) {
                // Primer enunciado: sube la versión y toma el candado de escritura. Cero filas = otra edición llegó
                // antes (409) o la posición ya está Suprimida (422).
                $afectadas = DB::table(self::TABLA)->where('id', $id)->where('version', $versionEsperada)
                    ->where('estado', '<>', EstadoPosicion::Suprimida->value)
                    ->update(['version' => DB::raw('version + 1'), 'updated_by' => $actorId, 'updated_at' => now()]);
                if ($afectadas === 0) {
                    $estado = DB::table(self::TABLA)->where('id', $id)->value('estado');
                    throw match (true) {
                        $estado === null => new PosicionNoEncontrada,
                        $estado === EstadoPosicion::Suprimida->value => PosicionInvalida::suprimida(),
                        default => new PosicionModificada,
                    };
                }

                $fresca = PosicionModel::query()->findOrFail($id);
                $anterior = $this->instantanea($fresca);
                $unidadVigente = (int) $fresca->unidad_organizacional_id;
                $supervisorVigente = $fresca->posicion_jefe_id === null ? null : (int) $fresca->posicion_jefe_id;

                // La máxima autoridad (sin supervisor) solo cambia de modalidad: su unidad es la raíz.
                if ($supervisorVigente === null) {
                    if ($datos->unidadId !== $unidadVigente || $datos->supervisorId !== null) {
                        throw PosicionInvalida::maximaAutoridadSoloModalidad();
                    }
                } else {
                    $jerarquia = $this->jerarquia();
                    if ($jerarquia->esRaiz($datos->unidadId)) {
                        throw PosicionInvalida::raizSoloMaximaAutoridad();
                    }
                    if ($datos->supervisorId === null) {
                        throw PosicionInvalida::supervisorObligatorio();
                    }

                    // Conservar lo que ya tiene siempre se acepta; elegir otra unidad o supervisor exige las reglas vigentes.
                    $cambiaUnidad = $datos->unidadId !== $unidadVigente;
                    if ($cambiaUnidad
                        && UnidadModel::query()->whereKey($datos->unidadId)->value('estado') !== EstadoUnidad::Activa->value) {
                        throw PosicionInvalida::unidadInactiva();
                    }
                    if ($datos->supervisorId !== $supervisorVigente) {
                        $this->validarSupervisor($id, $datos->supervisorId);
                    }
                    if ($cambiaUnidad || $datos->supervisorId !== $supervisorVigente) {
                        $this->validarJerarquia($datos->unidadId, $datos->supervisorId);
                    }
                    if ($cambiaUnidad) {
                        // La unidad nueva debe ser la misma o superior a la de cada subordinada directa vigente.
                        $debajo = DB::table(self::TABLA)->where('posicion_jefe_id', $id)
                            ->where('estado', '<>', EstadoPosicion::Suprimida->value)
                            ->pluck('unidad_organizacional_id')
                            ->filter(fn ($unidad) => ! $jerarquia->esIgualOSuperior($datos->unidadId, (int) $unidad))
                            ->count();
                        if ($debajo > 0) {
                            throw PosicionInvalida::unidadPorDebajoDeSubordinadas($debajo);
                        }
                    }
                }

                $fresca->forceFill([
                    'unidad_organizacional_id' => $datos->unidadId,
                    'posicion_jefe_id' => $datos->supervisorId,
                    'modalidad' => $datos->modalidad->value,
                ])->save();

                $this->registrarCambios($id, $this->comparador->comparar($anterior, $this->instantanea($fresca->refresh())), $actorId);
            });
        } catch (UniqueConstraintViolationException) {
            throw PosicionInvalida::maximaAutoridadYaExiste();
        }

        return $this->obtener($id) ?? throw new PosicionNoEncontrada;
    }

    public function suprimir(int $id, ?int $actorId): Posicion
    {
        DB::transaction(function () use ($id, $actorId) {
            // Una sola sentencia condicional: comprobar "Vacante y sin subordinadas vigentes" y cambiar el estado es
            // atómico, así no se cruza con una ocupación ni con una subordinada nueva. No toca versión ni updated_*.
            $afectadas = DB::table(self::TABLA)
                ->where('id', $id)
                ->where('estado', EstadoPosicion::Vacante->value)
                ->whereNotExists(function (Builder $subordinadas) {
                    $subordinadas->selectRaw('1')->from(self::TABLA.' as sub')
                        ->whereColumn('sub.posicion_jefe_id', self::TABLA.'.id')
                        ->where('sub.estado', '<>', EstadoPosicion::Suprimida->value);
                })
                ->update(['estado' => EstadoPosicion::Suprimida->value]);

            if ($afectadas === 0) {
                $estado = DB::table(self::TABLA)->where('id', $id)->value('estado');
                if ($estado === null) {
                    throw new PosicionNoEncontrada;
                }
                if ($estado === EstadoPosicion::Suprimida->value) {
                    return; // ya estaba suprimida: sin cambios ni historial
                }
                if ($estado === EstadoPosicion::Ocupada->value) {
                    throw PosicionNoSuprimible::ocupada();
                }

                throw PosicionNoSuprimible::conSubordinadas($this->subordinadasVigentes([$id])[$id] ?? 0);
            }

            $this->registrarCambios($id, [
                new CambioAuditado('estado', EstadoPosicion::Vacante->value, EstadoPosicion::Suprimida->value),
            ], $actorId);
        });

        return $this->obtener($id) ?? throw new PosicionNoEncontrada;
    }

    // ------------------------------------------------------------------ Apoyo

    /**
     * El supervisor elegido debe existir, no estar Suprimido, no ser la propia posición ni una de sus subordinadas
     * (se comprueba contra el árbol vigente dentro de la transacción de escritura).
     */
    private function validarSupervisor(int $id, int $supervisorId): void
    {
        if ($supervisorId === $id) {
            throw PosicionInvalida::supervisorEsLaMisma();
        }

        $estado = PosicionModel::query()->whereKey($supervisorId)->value('estado') ?? throw PosicionInvalida::supervisorInexistente();
        if ($estado === EstadoPosicion::Suprimida->value) {
            throw PosicionInvalida::supervisorSuprimido();
        }

        if ((new ArbolDeSupervision($this->arbolDeSupervision()))->esSubordinada($supervisorId, $id)) {
            throw PosicionInvalida::supervisorEsSubordinada();
        }
    }

    /** La supervisora debe estar en la misma unidad que la posición o en una unidad superior (jerarquía de unidades). */
    private function validarJerarquia(int $unidadId, int $supervisorId): void
    {
        $unidadDelSupervisor = (int) PosicionModel::query()->whereKey($supervisorId)->value('unidad_organizacional_id');

        if (! $this->jerarquia()->esIgualOSuperior($unidadDelSupervisor, $unidadId)) {
            throw PosicionInvalida::supervisorFueraDeJerarquia();
        }
    }

    private function jerarquia(): JerarquiaDeUnidades
    {
        return new JerarquiaDeUnidades($this->arbolDeUnidades()['arbol']);
    }

    private function consulta(): Builder
    {
        return DB::table('posiciones as p')
            ->join('puestos as pu', 'pu.id', '=', 'p.puesto_id')
            ->join('unidades_organizacionales as u', 'u.id', '=', 'p.unidad_organizacional_id')
            ->join('tipos_unidad as t', 't.id', '=', 'u.tipo_unidad_id')
            ->leftJoin('posiciones as s', 's.id', '=', 'p.posicion_jefe_id')
            ->leftJoin('puestos as sp', 'sp.id', '=', 's.puesto_id')
            ->select([
                'p.id', 'p.codigo', 'p.estado', 'p.modalidad', 'p.user_id', 'p.version',
                'p.created_by', 'p.created_at', 'p.updated_by', 'p.updated_at',
                'pu.id as puesto_id', 'pu.codigo as puesto_codigo', 'pu.nombre as puesto_nombre', 'pu.descripcion as puesto_descripcion',
                'u.id as unidad_id', 'u.codigo as unidad_codigo', 'u.nombre as unidad_nombre', 'u.estado as unidad_estado',
                't.id as tipo_id', 't.nombre as tipo_nombre', 't.nivel as tipo_nivel',
                's.id as sup_id', 's.codigo as sup_codigo', 'sp.nombre as sup_puesto', 's.user_id as sup_ocupante',
            ]);
    }

    private function aplicarFiltro(Builder $consulta, FiltroDePosiciones $filtro): void
    {
        match ($filtro->estado) {
            FiltroDePosiciones::TODAS => null,
            FiltroDePosiciones::VIGENTES => $consulta->whereIn('p.estado', [EstadoPosicion::Ocupada->value, EstadoPosicion::Vacante->value]),
            default => $consulta->where('p.estado', $filtro->estado),
        };

        if ($filtro->unidadId !== null) {
            ['arbol' => $arbol, 'nodos' => $nodos] = $this->arbolDeUnidades();
            $ids = isset($nodos[$filtro->unidadId]) ? [$filtro->unidadId, ...$arbol->descendientesDe($filtro->unidadId)] : [];
            $consulta->whereIn('p.unidad_organizacional_id', $ids);
        }

        $buscado = $filtro->q === null ? '' : PuestoModel::busquedaDeTexto($filtro->q);
        if ($buscado !== '') {
            $like = '%'.$buscado.'%';
            // El nombre de la unidad no tiene columna de búsqueda: se normaliza en memoria (hay pocas unidades).
            $unidadesQueCoinciden = DB::table('unidades_organizacionales')->get(['id', 'nombre'])
                ->filter(fn (object $u) => str_contains(PuestoModel::busquedaDeTexto((string) $u->nombre), $buscado))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();

            $consulta->where(function (Builder $q) use ($like, $unidadesQueCoinciden, $filtro) {
                $q->where('p.codigo', 'like', $like)
                    ->orWhere('pu.nombre_busqueda', 'like', $like)
                    ->orWhere('sp.nombre_busqueda', 'like', $like)
                    ->orWhere('s.codigo', 'like', $like);
                if ($unidadesQueCoinciden !== []) {
                    $q->orWhereIn('p.unidad_organizacional_id', $unidadesQueCoinciden);
                }
                if ($filtro->usuarioIds !== []) {
                    $q->orWhereIn('p.user_id', $filtro->usuarioIds);
                }
            });
        }
    }

    /**
     * @param  array<int, object>  $filas
     * @return array<int, Posicion>
     */
    private function construir(array $filas, bool $conSubordinadas = false): array
    {
        $ids = array_map(fn (object $f) => (int) $f->id, $filas);
        $vigentes = $this->subordinadasVigentes($ids);
        $detalle = $conSubordinadas ? $this->subordinadasDe($ids) : [];

        return array_map(function (object $f) use ($vigentes, $detalle) {
            $id = (int) $f->id;
            $editada = (int) $f->version > 1;

            return new Posicion(
                id: $id,
                codigo: (string) ($f->codigo ?? PosicionModel::codigoDeId($id)),
                estado: EstadoPosicion::from($f->estado),
                modalidad: Modalidad::from($f->modalidad),
                puesto: [
                    'id' => (int) $f->puesto_id,
                    'codigo' => (string) $f->puesto_codigo,
                    'nombre' => (string) $f->puesto_nombre,
                    'descripcion' => (string) $f->puesto_descripcion,
                ],
                unidad: [
                    'id' => (int) $f->unidad_id,
                    'codigo' => (string) $f->unidad_codigo,
                    'nombre' => (string) $f->unidad_nombre,
                    'estado' => (string) $f->unidad_estado,
                    'tipo' => ['id' => (int) $f->tipo_id, 'nombre' => (string) $f->tipo_nombre, 'nivel' => (int) $f->tipo_nivel],
                ],
                supervisor: $f->sup_id === null ? null : [
                    'id' => (int) $f->sup_id,
                    'codigo' => (string) ($f->sup_codigo ?? PosicionModel::codigoDeId((int) $f->sup_id)),
                    'puesto' => (string) $f->sup_puesto,
                    'ocupante_id' => $f->sup_ocupante === null ? null : (int) $f->sup_ocupante,
                ],
                ocupanteId: $f->user_id === null ? null : (int) $f->user_id,
                subordinadasVigentes: $vigentes[$id] ?? 0,
                version: (int) $f->version,
                subordinadas: $detalle[$id] ?? [],
                creadoPor: $f->created_by === null ? null : (int) $f->created_by,
                creadoEn: $this->fechaHora($f->created_at),
                modificadoPor: $editada && $f->updated_by !== null ? (int) $f->updated_by : null,
                modificadoEn: $editada ? $this->fechaHora($f->updated_at) : null,
            );
        }, $filas);
    }

    /**
     * Cantidad de subordinadas vigentes (no Suprimidas) de cada posición, en una sola consulta.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function subordinadasVigentes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table(self::TABLA)
            ->whereIn('posicion_jefe_id', $ids)
            ->where('estado', '<>', EstadoPosicion::Suprimida->value)
            ->groupBy('posicion_jefe_id')
            ->selectRaw('posicion_jefe_id, count(*) as total')
            ->pluck('total', 'posicion_jefe_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Subordinadas directas y vigentes de cada posición.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<int, array{id: int, codigo: string, puesto: string, estado: string, ocupante_id: int|null}>>
     */
    private function subordinadasDe(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $resultado = [];
        $filas = DB::table('posiciones as p')
            ->join('puestos as pu', 'pu.id', '=', 'p.puesto_id')
            ->whereIn('p.posicion_jefe_id', $ids)
            ->where('p.estado', '<>', EstadoPosicion::Suprimida->value)
            ->orderBy('p.id')
            ->get(['p.id', 'p.codigo', 'p.estado', 'p.posicion_jefe_id', 'p.user_id', 'pu.nombre as puesto']);

        foreach ($filas as $fila) {
            $resultado[(int) $fila->posicion_jefe_id][] = [
                'id' => (int) $fila->id,
                'codigo' => (string) ($fila->codigo ?? PosicionModel::codigoDeId((int) $fila->id)),
                'puesto' => (string) $fila->puesto,
                'estado' => (string) $fila->estado,
                'ocupante_id' => $fila->user_id === null ? null : (int) $fila->user_id,
            ];
        }

        return $resultado;
    }

    /**
     * Árbol de unidades (solo para ordenar, calcular niveles y expandir el filtro por rama).
     *
     * @return array{nodos: array<int, NodoDeUnidad>, arbol: ArbolDeUnidades}
     */
    private function arbolDeUnidades(): array
    {
        $nodos = DB::table('unidades_organizacionales as u')
            ->join('tipos_unidad as t', 't.id', '=', 'u.tipo_unidad_id')
            ->get(['u.id', 'u.codigo', 'u.nombre', 'u.unidad_padre_id', 'u.estado', 't.id as tipo_id', 't.nombre as tipo_nombre', 't.nivel as tipo_nivel'])
            ->mapWithKeys(fn (object $u) => [(int) $u->id => new NodoDeUnidad(
                (int) $u->id,
                (string) $u->codigo,
                (string) $u->nombre,
                new TipoDeUnidad((int) $u->tipo_id, (string) $u->tipo_nombre, (int) $u->tipo_nivel),
                $u->unidad_padre_id === null ? null : (int) $u->unidad_padre_id,
                EstadoUnidad::from($u->estado),
            )])->all();

        return ['nodos' => $nodos, 'arbol' => new ArbolDeUnidades($nodos)];
    }

    private function instantanea(PosicionModel $posicion): InstantaneaDePosicion
    {
        return new InstantaneaDePosicion(
            (int) $posicion->puesto_id,
            (int) $posicion->unidad_organizacional_id,
            $posicion->posicion_jefe_id === null ? null : (int) $posicion->posicion_jefe_id,
            Modalidad::from($posicion->modalidad),
            EstadoPosicion::from($posicion->estado),
        );
    }

    private function fechaHora(mixed $valor): ?string
    {
        return $valor === null ? null : Carbon::parse((string) $valor, 'UTC')->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * @param  array<int, CambioAuditado>  $cambios
     */
    private function registrarCambios(int $posicionId, array $cambios, ?int $actorId): void
    {
        foreach ($cambios as $cambio) {
            AuditoriaCambioModel::query()->create([
                'entidad' => self::ENTIDAD,
                'entidad_id' => $posicionId,
                'campo' => $cambio->campo,
                'valor_anterior' => $cambio->valorAnterior,
                'valor_nuevo' => $cambio->valorNuevo,
                'user_id' => $actorId,
            ]);
        }
    }

    // ------------------------------------------------- Lectura para otros módulos (GP-02)

    public function datosDe(int $id): ?array
    {
        return $this->datosPorIds([$id])[$id] ?? null;
    }

    public function datosPorIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $salida = [];
        foreach ($this->baseExterna()->whereIn('posiciones.id', array_values(array_unique($ids)))->get() as $fila) {
            $salida[(int) $fila->id] = $this->datosAArreglo($fila);
        }

        return $salida;
    }

    public function asignables(int $tenantId, ?string $texto, int $pagina, int $porPagina): array
    {
        $consulta = $this->baseExterna()
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
                'jefatura' => $fila->supervisor_puesto === null ? null : (string) $fila->supervisor_puesto,
            ])->all(),
            'total' => $total,
        ];
    }

    public function motivoNoAsignable(int $id, int $tenantId): ?string
    {
        $posicion = $this->datosDe($id);

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

    /** Consulta plana (con unión izquierda a la unidad y al supervisor) para los datos que leen otros módulos. */
    private function baseExterna(): Builder
    {
        return DB::table('posiciones')
            ->join('puestos', 'puestos.id', '=', 'posiciones.puesto_id')
            ->leftJoin('unidades_organizacionales', 'unidades_organizacionales.id', '=', 'posiciones.unidad_organizacional_id')
            ->leftJoin('posiciones as sup', 'sup.id', '=', 'posiciones.posicion_jefe_id')
            ->leftJoin('puestos as sup_puesto', 'sup_puesto.id', '=', 'sup.puesto_id')
            ->select([
                'posiciones.id', 'posiciones.codigo', 'posiciones.tenant_id', 'posiciones.estado', 'posiciones.modalidad',
                'posiciones.puesto_id', 'posiciones.posicion_jefe_id as supervisor_id', 'posiciones.user_id as ocupante_id',
                'puestos.nombre as puesto_nombre', 'puestos.estado as puesto_estado',
                'posiciones.unidad_organizacional_id as unidad_id',
                'unidades_organizacionales.nombre as unidad_nombre', 'unidades_organizacionales.estado as unidad_estado',
                'sup_puesto.nombre as supervisor_puesto',
            ]);
    }

    /**
     * @return array{id: int, codigo: string|null, tenant_id: int, estado: string, modalidad: string|null, puesto_id: int, puesto: string, puesto_activo: bool, unidad_id: int|null, unidad: string|null, unidad_activa: bool, jefatura: string|null, supervisor_id: int|null, ocupante_id: int|null}
     */
    private function datosAArreglo(object $fila): array
    {
        return [
            'id' => (int) $fila->id,
            'codigo' => $fila->codigo === null ? null : (string) $fila->codigo,
            'tenant_id' => (int) $fila->tenant_id,
            'estado' => (string) $fila->estado,
            'modalidad' => $fila->modalidad === null ? null : (string) $fila->modalidad,
            'puesto_id' => (int) $fila->puesto_id,
            'puesto' => (string) $fila->puesto_nombre,
            'puesto_activo' => $fila->puesto_estado === 'Activo',
            'unidad_id' => $fila->unidad_id === null ? null : (int) $fila->unidad_id,
            'unidad' => $fila->unidad_nombre === null ? null : (string) $fila->unidad_nombre,
            'unidad_activa' => $fila->unidad_estado === 'Activa',
            'jefatura' => $fila->supervisor_puesto === null ? null : (string) $fila->supervisor_puesto,
            'supervisor_id' => $fila->supervisor_id === null ? null : (int) $fila->supervisor_id,
            'ocupante_id' => $fila->ocupante_id === null ? null : (int) $fila->ocupante_id,
        ];
    }
}
