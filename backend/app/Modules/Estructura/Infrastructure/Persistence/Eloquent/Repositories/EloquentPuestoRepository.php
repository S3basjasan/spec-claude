<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Estructura\Domain\Entities\Puesto;
use App\Modules\Estructura\Domain\Exceptions\NombreDePuestoDuplicado;
use App\Modules\Estructura\Domain\Exceptions\PuestoConPosicionesEnUso;
use App\Modules\Estructura\Domain\Exceptions\PuestoModificado;
use App\Modules\Estructura\Domain\Exceptions\PuestoNoEncontrado;
use App\Modules\Estructura\Domain\Repositories\PuestoRepositoryInterface;
use App\Modules\Estructura\Domain\Services\ComparadorDeCambiosDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\CambioAuditado;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaAsociada;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaConGrados;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\DatosPuesto;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPosicion;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPuesto;
use App\Modules\Estructura\Domain\ValueObjects\FuncionPuesto;
use App\Modules\Estructura\Domain\ValueObjects\GradoLetra;
use App\Modules\Estructura\Domain\ValueObjects\PaginaPuestos;
use App\Modules\Estructura\Domain\ValueObjects\RequisitoPuesto;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoFuncion;
use App\Modules\Estructura\Domain\ValueObjects\TipoRequisito;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoCompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoFuncionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoRequisitoModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentPuestoRepository implements PuestoRepositoryInterface
{
    private const ENTIDAD = 'puesto';

    public function __construct(private readonly ComparadorDeCambiosDePuesto $comparador) {}

    // ---------------------------------------------------------------- Lecturas

    public function listar(?string $q, ?EstadoPuesto $estado, int $pagina, int $porPagina): PaginaPuestos
    {
        $consulta = PuestoModel::query();

        $texto = $q === null ? '' : trim($q);
        if ($texto !== '') {
            $porCodigo = '%'.addcslashes(mb_strtolower($texto), '%_\\').'%';
            $porNombre = '%'.addcslashes(PuestoModel::busquedaDeTexto($texto), '%_\\').'%';
            $consulta->where(function (Builder $condicion) use ($porCodigo, $porNombre) {
                $condicion->whereRaw('lower(codigo) like ? escape ?', [$porCodigo, '\\'])
                    ->orWhereRaw('nombre_busqueda like ? escape ?', [$porNombre, '\\']);
            });
        }

        if ($estado !== null) {
            $consulta->where('estado', $estado->value);
        }

        $total = (clone $consulta)->count();
        $puestos = $consulta->orderBy('nombre')->orderBy('id')
            ->skip(($pagina - 1) * $porPagina)
            ->take($porPagina)
            ->get();

        return new PaginaPuestos($puestos->map(fn (PuestoModel $p) => $this->aEntidad($p, false))->all(), $total);
    }

    public function obtener(int $id): ?Puesto
    {
        $puesto = $this->conListas()->find($id);

        return $puesto === null ? null : $this->aEntidad($puesto, true);
    }

    public function obtenerPorIds(array $ids): array
    {
        return $this->conListas()->whereIn('id', $ids)->orderBy('nombre')->get()
            ->map(fn (PuestoModel $p) => $this->aEntidad($p, true))->all();
    }

    public function obtenerActivos(): array
    {
        return $this->conListas()->where('estado', EstadoPuesto::Activo->value)->orderBy('nombre')->get()
            ->map(fn (PuestoModel $p) => $this->aEntidad($p, true))->all();
    }

    public function competenciasDisponibles(): array
    {
        return CompetenciaModel::query()
            ->with('grados.valor')
            ->where('estado', EstadoCompetencia::Activo->value)
            ->orderBy('nombre')
            ->get()
            ->map(fn (CompetenciaModel $c) => $this->conGrados($c))
            ->all();
    }

    public function competenciasPorIds(array $ids): array
    {
        $resultado = [];
        foreach (CompetenciaModel::query()->with('grados.valor')->whereIn('id', $ids)->get() as $competencia) {
            $resultado[$competencia->id] = $this->conGrados($competencia);
        }

        return $resultado;
    }

    // ------------------------------------------------------------- Escrituras

    public function crear(DatosPuesto $datos, ?int $actorId): Puesto
    {
        try {
            $id = DB::transaction(function () use ($datos, $actorId) {
                $puesto = PuestoModel::query()->create([
                    'nombre' => trim($datos->nombre),
                    'nombre_clave' => PuestoModel::claveDeNombre($datos->nombre),
                    'nombre_busqueda' => PuestoModel::busquedaDeTexto($datos->nombre),
                    'descripcion' => trim($datos->descripcion),
                    'mision' => trim($datos->mision),
                    'estado' => EstadoPuesto::Activo->value,
                    'version' => 1,
                    'created_by' => $actorId,
                ]);
                $puesto->forceFill(['codigo' => sprintf('PU-%03d', $puesto->id)])->save();

                $this->reemplazarListas($puesto->id, $datos);

                $nuevo = $this->obtener($puesto->id) ?? throw new PuestoNoEncontrado;
                $this->registrarCambios($puesto->id, $this->comparador->comparar(null, $nuevo->instantanea()), $actorId);

                return $puesto->id;
            });
        } catch (UniqueConstraintViolationException) {
            // Otra persona guardó un puesto con el mismo nombre entre la comprobación y la inserción.
            throw new NombreDePuestoDuplicado;
        }

        return $this->obtener($id) ?? throw new PuestoNoEncontrado;
    }

    public function actualizar(int $id, int $versionEsperada, DatosPuesto $datos, ?int $actorId): Puesto
    {
        try {
            DB::transaction(function () use ($id, $versionEsperada, $datos, $actorId) {
                $actual = $this->obtener($id) ?? throw new PuestoNoEncontrado;

                // Guardar sin cambios reales no modifica nada: ni filas, ni versión, ni historial.
                if ($this->sinCambios($actual, $datos)) {
                    return;
                }

                $afectadas = DB::table('puestos')
                    ->where('id', $id)
                    ->where('version', $versionEsperada)
                    ->update([
                        'nombre' => trim($datos->nombre),
                        'nombre_clave' => PuestoModel::claveDeNombre($datos->nombre),
                        'nombre_busqueda' => PuestoModel::busquedaDeTexto($datos->nombre),
                        'descripcion' => trim($datos->descripcion),
                        'mision' => trim($datos->mision),
                        'version' => DB::raw('version + 1'),
                        'updated_by' => $actorId,
                        'updated_at' => now(),
                    ]);

                if ($afectadas === 0) {
                    throw new PuestoModificado;
                }

                $this->reemplazarListas($id, $datos);

                $nuevo = $this->obtener($id) ?? throw new PuestoNoEncontrado;
                // El orden de las funciones no se audita: reordenar no escribe filas en el historial.
                $this->registrarCambios($id, $this->comparador->comparar($actual->instantanea(), $nuevo->instantanea()), $actorId);
            });
        } catch (UniqueConstraintViolationException) {
            throw new NombreDePuestoDuplicado;
        }

        return $this->obtener($id) ?? throw new PuestoNoEncontrado;
    }

    public function cambiarEstado(int $id, EstadoPuesto $nuevo, ?int $actorId): Puesto
    {
        DB::transaction(function () use ($id, $nuevo, $actorId) {
            if (! PuestoModel::query()->whereKey($id)->exists()) {
                throw new PuestoNoEncontrado;
            }

            // Una sola sentencia condicional: comprobar "sin posiciones en uso" y cambiar el estado es atómico,
            // sin ventana entre ambas. No toca `version`, `updated_by` ni `updated_at`.
            $consulta = DB::table('puestos')->where('id', $id)->where('estado', $nuevo->opuesto()->value);
            if ($nuevo === EstadoPuesto::Inactivo) {
                $consulta->whereNotExists(function (QueryBuilder $posiciones) {
                    $posiciones->selectRaw('1')
                        ->from('posiciones')
                        ->whereColumn('posiciones.puesto_id', 'puestos.id')
                        ->whereIn('posiciones.estado', $this->estadosEnUso());
                });
            }

            if ($consulta->update(['estado' => $nuevo->value]) === 1) {
                $this->registrarCambios($id, [new CambioAuditado('estado', $nuevo->opuesto()->value, $nuevo->value)], $actorId);

                return;
            }

            // No cambió nada: ya estaba en ese estado (sin historial) o tiene posiciones en uso.
            if (DB::table('puestos')->where('id', $id)->value('estado') === $nuevo->value) {
                return;
            }

            throw new PuestoConPosicionesEnUso($this->contarPosicionesEnUso($id));
        });

        return $this->obtener($id) ?? throw new PuestoNoEncontrado;
    }

    // ------------------------------------------------------------------ Apoyo

    /** @return Builder<PuestoModel> */
    private function conListas(): Builder
    {
        return PuestoModel::query()->with(['funciones', 'requisitos', 'competenciasAsignadas.competencia', 'competenciasAsignadas.grado.valor']);
    }

    /**
     * Borra y reinserta las funciones (orden desde 1), los requisitos y las competencias del puesto.
     * Las filas hijas no tienen otras referencias; su trazabilidad está en `puestos` y `auditoria_cambios`.
     */
    private function reemplazarListas(int $puestoId, DatosPuesto $datos): void
    {
        PuestoFuncionModel::query()->where('puesto_id', $puestoId)->delete();
        PuestoRequisitoModel::query()->where('puesto_id', $puestoId)->delete();
        PuestoCompetenciaModel::query()->where('puesto_id', $puestoId)->delete();

        foreach (array_values($datos->funciones) as $indice => $funcion) {
            PuestoFuncionModel::query()->create([
                'puesto_id' => $puestoId,
                'descripcion' => trim($funcion->descripcion),
                'tipo' => $funcion->tipo->value,
                'orden' => $indice + 1,
            ]);
        }

        foreach ($datos->requisitos as $requisito) {
            PuestoRequisitoModel::query()->create([
                'puesto_id' => $puestoId,
                'tipo' => $requisito->tipo->value,
                'descripcion' => trim($requisito->descripcion),
            ]);
        }

        $grados = $this->idsDeGrados(array_map(fn ($asignacion) => $asignacion->competenciaId, $datos->competencias));

        foreach ($datos->competencias as $asignacion) {
            PuestoCompetenciaModel::query()->create([
                'puesto_id' => $puestoId,
                'competencia_id' => $asignacion->competenciaId,
                // El grado requerido apunta al grado de la competencia: cambiar su escala no lo altera.
                'competencia_grado_id' => $grados[$asignacion->competenciaId][$asignacion->grado],
            ]);
        }
    }

    /**
     * Id del grado de cada competencia por clave posicional (A = orden 1 … D = orden 4).
     *
     * @param  array<int, int>  $competenciaIds
     * @return array<int, array<string, int>>
     */
    private function idsDeGrados(array $competenciaIds): array
    {
        $mapa = [];

        foreach (CompetenciaGradoModel::query()->with('valor')->whereIn('competencia_id', array_unique($competenciaIds))->get() as $grado) {
            $mapa[(int) $grado->competencia_id][GradoLetra::desdeOrden((int) $grado->valor->orden)] = (int) $grado->id;
        }

        return $mapa;
    }

    /**
     * Cambio real = distinto contenido. Las funciones se comparan en su orden; los requisitos y las competencias,
     * como conjuntos (no tienen orden propio).
     */
    private function sinCambios(Puesto $actual, DatosPuesto $datos): bool
    {
        if (trim($datos->nombre) !== $actual->nombre
            || trim($datos->descripcion) !== $actual->descripcion
            || trim($datos->mision) !== $actual->mision) {
            return false;
        }

        $funciones = array_map(fn (FuncionPuesto $f) => [trim($f->descripcion), $f->tipo->value], array_values($datos->funciones));
        $funcionesActuales = array_map(fn (FuncionPuesto $f) => [$f->descripcion, $f->tipo->value], $actual->funciones);
        if ($funciones !== $funcionesActuales) {
            return false;
        }

        $requisitos = array_map(fn (RequisitoPuesto $r) => $r->tipo->value.'|'.trim($r->descripcion), $datos->requisitos);
        $requisitosActuales = array_map(fn (RequisitoPuesto $r) => $r->tipo->value.'|'.$r->descripcion, $actual->requisitos);
        sort($requisitos);
        sort($requisitosActuales);
        if ($requisitos !== $requisitosActuales) {
            return false;
        }

        $competencias = [];
        foreach ($datos->competencias as $asignacion) {
            $competencias[$asignacion->competenciaId] = $asignacion->grado;
        }
        $competenciasActuales = [];
        foreach ($actual->competencias as $competencia) {
            $competenciasActuales[$competencia->competencia->id] = $competencia->gradoRequerido;
        }
        ksort($competencias);
        ksort($competenciasActuales);

        return $competencias === $competenciasActuales;
    }

    /**
     * @param  array<int, CambioAuditado>  $cambios
     */
    private function registrarCambios(int $puestoId, array $cambios, ?int $actorId): void
    {
        foreach ($cambios as $cambio) {
            AuditoriaCambioModel::query()->create([
                'entidad' => self::ENTIDAD,
                'entidad_id' => $puestoId,
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
    private function estadosEnUso(): array
    {
        return array_map(
            fn (EstadoPosicion $estado) => $estado->value,
            array_values(array_filter(EstadoPosicion::cases(), fn (EstadoPosicion $e) => $e->enUso())),
        );
    }

    private function contarPosicionesEnUso(int $puestoId): int
    {
        return PosicionModel::query()->where('puesto_id', $puestoId)->whereIn('estado', $this->estadosEnUso())->count();
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

    private function conGrados(CompetenciaModel $competencia): CompetenciaConGrados
    {
        return new CompetenciaConGrados(
            $this->asociada($competencia),
            $competencia->gradosPorLetra(),
        );
    }

    private function aEntidad(PuestoModel $puesto, bool $conListas): Puesto
    {
        $funciones = [];
        $requisitos = [];
        $competencias = [];

        if ($conListas) {
            $funciones = $puesto->funciones
                ->map(fn (PuestoFuncionModel $f) => new FuncionPuesto((int) $f->orden, $f->descripcion, TipoFuncion::from($f->tipo)))
                ->values()->all();

            $requisitos = $puesto->requisitos
                ->map(fn (PuestoRequisitoModel $r) => new RequisitoPuesto(TipoRequisito::from($r->tipo), $r->descripcion))
                ->sortBy(fn (RequisitoPuesto $r) => $r->tipo->orden())
                ->values()->all();

            $competencias = $puesto->competenciasAsignadas
                ->map(fn (PuestoCompetenciaModel $c) => new CompetenciaDePuesto($this->asociada($c->competencia), $c->grado_requerido))
                ->sortBy(fn (CompetenciaDePuesto $c) => mb_strtolower($c->competencia->nombre))
                ->values()->all();
        }

        return new Puesto(
            id: $puesto->id,
            codigo: (string) $puesto->codigo,
            nombre: $puesto->nombre,
            descripcion: $puesto->descripcion,
            mision: $puesto->mision,
            estado: EstadoPuesto::from($puesto->estado),
            version: (int) $puesto->version,
            creadoPor: $puesto->created_by === null ? null : (int) $puesto->created_by,
            modificadoPor: $puesto->updated_by === null ? null : (int) $puesto->updated_by,
            funciones: $funciones,
            requisitos: $requisitos,
            competencias: $competencias,
        );
    }
}
