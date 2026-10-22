<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Desarrollo\Domain\Entities\Adecuacion;
use App\Modules\Desarrollo\Domain\Entities\ParametrosDeAdecuacion;
use App\Modules\Desarrollo\Domain\Entities\ResultadoDeAdecuacion;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\CodigoDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeResultado;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Domain\ValueObjects\PesosPorDimension;
use App\Modules\Desarrollo\Domain\ValueObjects\Preparacion;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AdecuacionDetalleModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AdecuacionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\HistorialParametrosAdecuacionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ParametrosAdecuacionModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentAdecuacionesRepository implements AdecuacionesRepositoryInterface
{
    private const INTENTOS_DE_CODIGO = 5;

    public function guardar(Adecuacion $adecuacion): Adecuacion
    {
        for ($intento = 1; ; $intento++) {
            try {
                return DB::transaction(fn () => $this->insertar($adecuacion));
            } catch (UniqueConstraintViolationException $e) {
                if ($intento >= self::INTENTOS_DE_CODIGO) {
                    throw $e;
                }
            }
        }
    }

    public function obtener(int $tenantId, int $id): ?Adecuacion
    {
        $modelo = AdecuacionModel::query()->with('detalles')->where('tenant_id', $tenantId)->whereKey($id)->first();

        return $modelo === null ? null : $this->aEntidad($modelo);
    }

    public function esVigente(int $tenantId, Adecuacion $adecuacion): bool
    {
        $ultimo = AdecuacionModel::query()
            ->where('tenant_id', $tenantId)
            ->where('colaborador_id', $adecuacion->colaboradorId)
            ->where('puesto_objetivo_id', $adecuacion->puestoObjetivoId)
            ->max('id');

        return (int) $ultimo === $adecuacion->id;
    }

    public function listar(int $tenantId, array $filtros, int $pagina, int $porPagina): array
    {
        $consulta = AdecuacionModel::query()->where('adecuaciones.tenant_id', $tenantId);
        $this->aplicarFiltrosDeLista($consulta, $filtros);

        $total = (clone $consulta)->count();
        $filas = $consulta
            ->select('adecuaciones.*')
            ->selectRaw('(select count(*) from adecuacion_detalles d where d.adecuacion_id = adecuaciones.id and d.estado = ?) as brechas', [EstadoDeResultado::ConBrecha->value])
            ->selectRaw('(adecuaciones.id = (select max(a2.id) from adecuaciones a2 where a2.tenant_id = adecuaciones.tenant_id and a2.colaborador_id = adecuaciones.colaborador_id and a2.puesto_objetivo_id = adecuaciones.puesto_objetivo_id)) as es_vigente')
            ->orderByDesc('adecuaciones.id')
            ->forPage($pagina, $porPagina)
            ->get();

        return [
            'items' => $filas->map(fn (AdecuacionModel $m) => [
                'id' => (int) $m->id,
                'codigo' => (string) $m->codigo,
                'creado_en' => $this->fecha($m->created_at)->format('Y-m-d H:i:s'),
                'colaborador_id' => (int) $m->colaborador_id,
                'puesto_objetivo_id' => (int) $m->puesto_objetivo_id,
                'preparacion' => (string) $m->preparacion,
                'brechas' => (int) $m->getAttribute('brechas'),
                'es_vigente' => (bool) $m->getAttribute('es_vigente'),
            ])->all(),
            'total' => $total,
        ];
    }

    public function vigentes(int $tenantId, array $filtros = []): array
    {
        $consulta = $this->consultaDeVigentes($tenantId);

        if (! empty($filtros['preparacion'])) {
            $consulta->where('adecuaciones.preparacion', $filtros['preparacion']);
        }
        if (! empty($filtros['puesto_objetivo_id'])) {
            $consulta->where('adecuaciones.puesto_objetivo_id', (int) $filtros['puesto_objetivo_id']);
        }
        if (isset($filtros['colaborador_ids'])) {
            $consulta->whereIn('adecuaciones.colaborador_id', array_map('intval', $filtros['colaborador_ids']));
        }
        if (! empty($filtros['ruta_id'])) {
            $consulta->whereIn('adecuaciones.inscripcion_id', DB::table('ruta_inscripciones')
                ->where('ruta_id', (int) $filtros['ruta_id'])->select('id'));
        }

        return $this->aEntidades($consulta->orderByDesc('adecuaciones.id')->with('detalles')->get());
    }

    public function vigentesDeColaborador(int $tenantId, int $colaboradorId): array
    {
        return $this->aEntidades(
            $this->consultaDeVigentes($tenantId)->where('adecuaciones.colaborador_id', $colaboradorId)
                ->orderByDesc('adecuaciones.id')->with('detalles')->get(),
        );
    }

    public function ultimasDeColaborador(int $tenantId, int $colaboradorId, int $limite): array
    {
        return $this->aEntidades(
            AdecuacionModel::query()->where('tenant_id', $tenantId)->where('colaborador_id', $colaboradorId)
                ->orderByDesc('id')->limit($limite)->with('detalles')->get(),
        );
    }

    public function rutasDeInscripciones(array $inscripcionIds): array
    {
        if ($inscripcionIds === []) {
            return [];
        }

        $salida = [];
        $filas = DB::table('ruta_inscripciones as i')
            ->join('rutas as r', 'r.id', '=', 'i.ruta_id')
            ->whereIn('i.id', $inscripcionIds)
            ->get(['i.id as inscripcion_id', 'r.id as ruta_id', 'r.nombre']);
        foreach ($filas as $f) {
            $salida[(int) $f->inscripcion_id] = ['id' => (int) $f->ruta_id, 'nombre' => (string) $f->nombre];
        }

        return $salida;
    }

    public function obtenerParametros(int $tenantId): ParametrosDeAdecuacion
    {
        $modelo = ParametrosAdecuacionModel::query()->firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'peso_competencias' => 40, 'peso_conocimientos' => 30, 'peso_experiencia' => 30,
                'umbral_meses' => ParametrosDeAdecuacion::UMBRAL_INICIAL,
            ],
        );

        return new ParametrosDeAdecuacion(
            new PesosPorDimension((int) $modelo->peso_competencias, (int) $modelo->peso_conocimientos, (int) $modelo->peso_experiencia),
            (int) $modelo->umbral_meses,
        );
    }

    public function guardarParametros(int $tenantId, ParametrosDeAdecuacion $parametros, array $cambios, int $actorId): void
    {
        DB::transaction(function () use ($tenantId, $parametros, $cambios, $actorId) {
            $this->obtenerParametros($tenantId);
            ParametrosAdecuacionModel::query()->where('tenant_id', $tenantId)->update([
                'peso_competencias' => $parametros->pesos->competencias,
                'peso_conocimientos' => $parametros->pesos->conocimientos,
                'peso_experiencia' => $parametros->pesos->experiencia,
                'umbral_meses' => $parametros->umbralMeses,
                'updated_by' => $actorId,
                'updated_at' => now(),
            ]);

            foreach ($cambios as $c) {
                HistorialParametrosAdecuacionModel::query()->create([
                    'tenant_id' => $tenantId,
                    'campo' => $c['campo'],
                    'valor_anterior' => $c['anterior'],
                    'valor_nuevo' => $c['nuevo'],
                    'user_id' => $actorId,
                ]);
            }
        });
    }

    public function historialDeParametros(int $tenantId, int $pagina, int $porPagina): array
    {
        $consulta = HistorialParametrosAdecuacionModel::query()->where('tenant_id', $tenantId);
        $total = (clone $consulta)->count();

        return [
            'items' => $consulta->orderByDesc('id')->forPage($pagina, $porPagina)->get()->map(fn (HistorialParametrosAdecuacionModel $h) => [
                'campo' => (string) $h->campo,
                'valor_anterior' => (string) $h->valor_anterior,
                'valor_nuevo' => (string) $h->valor_nuevo,
                'user_id' => (int) $h->user_id,
                'fecha' => $this->fecha($h->created_at)->format('Y-m-d H:i:s'),
            ])->all(),
            'total' => $total,
        ];
    }

    private function insertar(Adecuacion $adecuacion): Adecuacion
    {
        $codigo = $this->siguienteCodigo($adecuacion->tenantId);
        $modelo = AdecuacionModel::query()->create([
            'tenant_id' => $adecuacion->tenantId,
            'codigo' => $codigo->valor(),
            'colaborador_id' => $adecuacion->colaboradorId,
            'puesto_actual_id' => $adecuacion->puestoActualId,
            'puesto_objetivo_id' => $adecuacion->puestoObjetivoId,
            'inscripcion_id' => $adecuacion->inscripcionId,
            'nivel_objetivo_id' => $adecuacion->nivelObjetivoId,
            'experiencia_requerida_anios' => $adecuacion->experienciaRequeridaAnios,
            'preparacion' => $adecuacion->preparacion->value,
            'umbral_meses' => $adecuacion->umbralMeses,
            'observaciones' => $adecuacion->observaciones,
            'created_by' => $adecuacion->creadoPor,
        ]);

        foreach ($adecuacion->resultados as $r) {
            AdecuacionDetalleModel::query()->create([
                'adecuacion_id' => $modelo->id,
                'dimension' => $r->dimension->value,
                'tipo_elemento' => $r->tipoElemento,
                'elemento_id' => $r->elementoId,
                'nombre_elemento' => $r->nombreElemento,
                'requerido_actual_nivel' => $r->requeridoActualNivel?->value,
                'requerido_objetivo_nivel' => $r->requeridoObjetivoNivel?->value,
                'valor_nivel' => $r->valorNivel?->value,
                'requerido_actual_anios' => $r->requeridoActualAnios,
                'requerido_objetivo_anios' => $r->requeridoObjetivoAnios,
                'valor_anios' => $r->valorAnios,
                'diferencia' => $r->diferencia,
                'estado' => $r->estado->value,
                'tiempo_cierre_meses' => $r->tiempoCierreMeses,
            ]);
        }

        return $this->aEntidad($modelo->load('detalles'));
    }

    private function siguienteCodigo(int $tenantId): CodigoDeAdecuacion
    {
        $ultimo = AdecuacionModel::query()->where('tenant_id', $tenantId)->orderByDesc('id')->value('codigo');

        return $ultimo === null ? CodigoDeAdecuacion::desdeNumero(1) : CodigoDeAdecuacion::desdeTexto((string) $ultimo)->siguiente();
    }

    /** @return Builder<AdecuacionModel> */
    private function consultaDeVigentes(int $tenantId): Builder
    {
        return AdecuacionModel::query()
            ->where('adecuaciones.tenant_id', $tenantId)
            ->whereIn('adecuaciones.id', DB::table('adecuaciones as a2')
                ->selectRaw('max(a2.id)')->where('a2.tenant_id', $tenantId)->groupBy('a2.colaborador_id', 'a2.puesto_objetivo_id'));
    }

    /**
     * @param  Builder<AdecuacionModel>  $consulta
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosDeLista(Builder $consulta, array $filtros): void
    {
        if (! empty($filtros['fecha_desde'])) {
            $consulta->whereDate('adecuaciones.created_at', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $consulta->whereDate('adecuaciones.created_at', '<=', $filtros['fecha_hasta']);
        }
        if (! empty($filtros['colaborador_id'])) {
            $consulta->where('adecuaciones.colaborador_id', (int) $filtros['colaborador_id']);
        }
        if (! empty($filtros['puesto_objetivo_id'])) {
            $consulta->where('adecuaciones.puesto_objetivo_id', (int) $filtros['puesto_objetivo_id']);
        }
        if (! empty($filtros['preparacion'])) {
            $consulta->where('adecuaciones.preparacion', $filtros['preparacion']);
        }
    }

    /**
     * @param  Collection<int, AdecuacionModel>  $modelos
     * @return list<Adecuacion>
     */
    private function aEntidades(Collection $modelos): array
    {
        return $modelos->map(fn (AdecuacionModel $m) => $this->aEntidad($m))->values()->all();
    }

    private function aEntidad(AdecuacionModel $m): Adecuacion
    {
        return new Adecuacion(
            (int) $m->id,
            (int) $m->tenant_id,
            CodigoDeAdecuacion::desdeTexto((string) $m->codigo),
            (int) $m->colaborador_id,
            $m->puesto_actual_id === null ? null : (int) $m->puesto_actual_id,
            (int) $m->puesto_objetivo_id,
            $m->inscripcion_id === null ? null : (int) $m->inscripcion_id,
            $m->nivel_objetivo_id === null ? null : (int) $m->nivel_objetivo_id,
            $m->experiencia_requerida_anios === null ? null : (float) $m->experiencia_requerida_anios,
            Preparacion::from((string) $m->preparacion),
            (int) $m->umbral_meses,
            $m->observaciones,
            (int) $m->created_by,
            $this->fecha($m->created_at),
            $m->detalles->map(fn (AdecuacionDetalleModel $d) => $this->aResultado($d))->values()->all(),
        );
    }

    private function aResultado(AdecuacionDetalleModel $d): ResultadoDeAdecuacion
    {
        return new ResultadoDeAdecuacion(
            DimensionDeAdecuacion::from((string) $d->dimension),
            $d->tipo_elemento,
            $d->elemento_id === null ? null : (int) $d->elemento_id,
            $d->nombre_elemento,
            $d->requerido_actual_nivel === null ? null : NivelGrado::from($d->requerido_actual_nivel),
            $d->requerido_objetivo_nivel === null ? null : NivelGrado::from($d->requerido_objetivo_nivel),
            $d->valor_nivel === null ? null : NivelGrado::from($d->valor_nivel),
            $d->requerido_actual_anios,
            $d->requerido_objetivo_anios,
            $d->valor_anios,
            $d->diferencia,
            EstadoDeResultado::from((string) $d->estado),
            $d->tiempo_cierre_meses,
        );
    }

    private function fecha(mixed $valor): DateTimeImmutable
    {
        return $valor === null ? new DateTimeImmutable : new DateTimeImmutable((string) $valor);
    }
}
