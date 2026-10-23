<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Desarrollo\Domain\Entities\AccionDePdi;
use App\Modules\Desarrollo\Domain\Entities\CompromisoDePdi;
use App\Modules\Desarrollo\Domain\Entities\HitoDePdi;
use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\Exceptions\PlanDuplicadoPorTipo;
use App\Modules\Desarrollo\Domain\Exceptions\VersionDePdiDesfasada;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\CodigoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeHito;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\PeriodoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\ResultadoDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\ResultadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeHistorialPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiCompromisoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHitoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiModel;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentPdiRepository implements RepositorioDePdiInterface
{
    private const INTENTOS_DE_CODIGO = 5;

    public function transaccion(callable $accion): mixed
    {
        return DB::transaction(fn () => $accion());
    }

    public function crear(Pdi $pdi, int $actorId): Pdi
    {
        for ($intento = 1; ; $intento++) {
            try {
                return DB::transaction(fn () => $this->insertar($pdi, $actorId));
            } catch (UniqueConstraintViolationException $e) {
                $existente = $this->planAbierto($pdi->tenantId, $pdi->colaboradorId, $pdi->tipo);
                if ($existente !== null) {
                    throw new PlanDuplicadoPorTipo($existente['codigo'], $existente['id']);
                }
                if ($intento >= self::INTENTOS_DE_CODIGO) {
                    throw $e;
                }
            }
        }
    }

    public function obtener(int $tenantId, int $id): ?Pdi
    {
        $modelo = PdiModel::query()->with(['compromisos.acciones', 'hitos'])->where('tenant_id', $tenantId)->whereKey($id)->first();

        return $modelo === null ? null : $this->aEntidad($modelo);
    }

    public function listar(int $tenantId, array $filtros): array
    {
        return PdiModel::query()
            ->with(['compromisos.acciones', 'hitos'])
            ->where('tenant_id', $tenantId)
            ->when(! empty($filtros['tipo']), fn ($q) => $q->where('tipo', $filtros['tipo']))
            ->when(! empty($filtros['estado']), fn ($q) => $q->where('estado', $filtros['estado']))
            ->when(! empty($filtros['colaborador_id']), fn ($q) => $q->where('colaborador_id', (int) $filtros['colaborador_id']))
            ->when(! empty($filtros['responsable_id']), fn ($q) => $q->where('responsable_id', (int) $filtros['responsable_id']))
            ->when(! empty($filtros['fecha_desde']), fn ($q) => $q->where('fecha_fin', '>=', $filtros['fecha_desde']))
            ->when(! empty($filtros['fecha_hasta']), fn ($q) => $q->where('fecha_fin', '<=', $filtros['fecha_hasta']))
            ->orderByDesc('id')
            ->get()
            ->map(fn (PdiModel $m) => $this->aEntidad($m))
            ->all();
    }

    public function indicadores(int $tenantId, string $hoy): array
    {
        $porEstado = PdiModel::query()->where('tenant_id', $tenantId)->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $vencidos = PdiHitoModel::query()
            ->join('pdis', 'pdis.id', '=', 'pdi_hitos.pdi_id')
            ->where('pdis.tenant_id', $tenantId)
            ->where('pdis.estado', '<>', EstadoDePdi::Cerrado->value)
            ->where('pdi_hitos.estado', EstadoDeHito::Pendiente->value)
            ->where('pdi_hitos.fecha', '<', $hoy)
            ->count();

        return [
            'borrador' => (int) ($porEstado[EstadoDePdi::Borrador->value] ?? 0),
            'acordado' => (int) ($porEstado[EstadoDePdi::Acordado->value] ?? 0),
            'cerrado' => (int) ($porEstado[EstadoDePdi::Cerrado->value] ?? 0),
            'hitos_vencidos' => $vencidos,
        ];
    }

    public function abiertosDeColaboradores(int $tenantId, array $colaboradorIds): array
    {
        if ($colaboradorIds === []) {
            return [];
        }

        return PdiModel::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('colaborador_id', $colaboradorIds)
            ->where('estado', '<>', EstadoDePdi::Cerrado->value)
            ->get(['colaborador_id', 'tipo', 'puesto_objetivo_id'])
            ->map(fn (PdiModel $m) => [
                'colaborador_id' => (int) $m->colaborador_id,
                'tipo' => (string) $m->tipo,
                'puesto_objetivo_id' => $m->puesto_objetivo_id === null ? null : (int) $m->puesto_objetivo_id,
            ])->all();
    }

    public function deColaborador(int $tenantId, int $colaboradorId): array
    {
        return PdiModel::query()
            ->with(['compromisos.acciones', 'hitos'])
            ->where('tenant_id', $tenantId)
            ->where('colaborador_id', $colaboradorId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (PdiModel $m) => $this->aEntidad($m))
            ->all();
    }

    public function actualizarCabecera(Pdi $pdi, int $versionEsperada, int $actorId): void
    {
        $afectadas = PdiModel::query()
            ->where('tenant_id', $pdi->tenantId)
            ->whereKey($pdi->id)
            ->where('version', $versionEsperada)
            ->update($this->columnasDeCabecera($pdi) + ['updated_by' => $actorId, 'updated_at' => now()]);

        if ($afectadas === 0) {
            throw new VersionDePdiDesfasada((int) PdiModel::query()->whereKey($pdi->id)->value('version'));
        }
    }

    public function reemplazarEstructura(Pdi $pdi, int $actorId): Pdi
    {
        return DB::transaction(function () use ($pdi, $actorId) {
            $compromisoIds = PdiCompromisoModel::query()->where('pdi_id', $pdi->id)->pluck('id');
            PdiAccionModel::query()->whereIn('compromiso_id', $compromisoIds)->delete();
            PdiCompromisoModel::query()->where('pdi_id', $pdi->id)->delete();
            PdiHitoModel::query()->where('pdi_id', $pdi->id)->delete();

            $this->insertarHijos($pdi, (int) $pdi->id, $actorId);

            return $this->obtener($pdi->tenantId, (int) $pdi->id);
        });
    }

    public function planAbierto(int $tenantId, int $colaboradorId, TipoDePdi $tipo): ?array
    {
        $fila = PdiModel::query()
            ->where('tenant_id', $tenantId)
            ->where('colaborador_id', $colaboradorId)
            ->where('tipo', $tipo->value)
            ->where('estado', '<>', EstadoDePdi::Cerrado->value)
            ->first(['id', 'codigo']);

        return $fila === null ? null : ['id' => (int) $fila->id, 'codigo' => (string) $fila->codigo];
    }

    public function obtenerAccion(int $tenantId, int $accionId): ?array
    {
        $modelo = PdiAccionModel::query()->where('tenant_id', $tenantId)->whereKey($accionId)->first();
        if ($modelo === null) {
            return null;
        }
        $pdiId = (int) PdiCompromisoModel::query()->whereKey($modelo->compromiso_id)->value('pdi_id');

        return ['pdi_id' => $pdiId, 'accion' => $this->aAccion($modelo)];
    }

    public function actualizarAccion(int $accionId, EstadoDeAccionPdi $estado, bool $congelado, ?int $planAccionId, int $actorId): void
    {
        PdiAccionModel::query()->whereKey($accionId)->update([
            'estado' => $estado->value,
            'estado_congelado' => $congelado,
            'plan_accion_id' => $planAccionId,
            'updated_by' => $actorId,
            'updated_at' => now(),
        ]);
    }

    public function obtenerHito(int $tenantId, int $hitoId): ?array
    {
        $modelo = PdiHitoModel::query()->where('tenant_id', $tenantId)->whereKey($hitoId)->first();

        return $modelo === null ? null : ['pdi_id' => (int) $modelo->pdi_id, 'hito' => $this->aHito($modelo)];
    }

    public function actualizarHito(int $hitoId, EstadoDeHito $estado, ?string $observaciones, int $actorId, DateTimeImmutable $ahora): void
    {
        PdiHitoModel::query()->whereKey($hitoId)->update([
            'estado' => $estado->value,
            'observaciones' => $observaciones,
            'registrado_by' => $actorId,
            'registrado_at' => $ahora->format('Y-m-d H:i:s'),
            'updated_at' => now(),
        ]);
    }

    public function guardarResultadosDeCompromisos(array $resultados): void
    {
        foreach ($resultados as $compromisoId => $resultado) {
            PdiCompromisoModel::query()->whereKey($compromisoId)->update(['resultado' => $resultado->value, 'updated_at' => now()]);
        }
    }

    public function registrarHistorial(int $tenantId, int $pdiId, TipoDeHistorialPdi $tipo, ?int $referenciaId, ?string $anterior, ?string $nuevo, ?string $motivo, int $usuarioId): void
    {
        PdiHistorialModel::query()->create([
            'tenant_id' => $tenantId,
            'pdi_id' => $pdiId,
            'tipo' => $tipo->value,
            'referencia_id' => $referenciaId,
            'valor_anterior' => $anterior === null ? null : mb_substr($anterior, 0, 255),
            'valor_nuevo' => $nuevo === null ? null : mb_substr($nuevo, 0, 255),
            'motivo' => $motivo === null ? null : mb_substr($motivo, 0, 1000),
            'user_id' => $usuarioId,
            'created_at' => now(),
        ]);
    }

    public function historial(int $tenantId, int $pdiId, int $pagina, int $porPagina): array
    {
        $consulta = PdiHistorialModel::query()->where('tenant_id', $tenantId)->where('pdi_id', $pdiId);
        $total = (clone $consulta)->count();
        $filas = $consulta->orderByDesc('id')->forPage($pagina, $porPagina)->get();

        return [
            'items' => $filas->map(fn (PdiHistorialModel $h) => [
                'id' => (int) $h->id,
                'fecha' => $this->fecha($h->created_at)->format('Y-m-d H:i:s'),
                'usuario_id' => (int) $h->user_id,
                'tipo' => (string) $h->tipo,
                'referencia_id' => $h->referencia_id === null ? null : (int) $h->referencia_id,
                'valor_anterior' => $h->valor_anterior,
                'valor_nuevo' => $h->valor_nuevo,
                'motivo' => $h->motivo,
            ])->all(),
            'total' => $total,
        ];
    }

    private function insertar(Pdi $pdi, int $actorId): Pdi
    {
        $ultimo = (int) PdiModel::query()
            ->where('tenant_id', $pdi->tenantId)
            ->selectRaw('max(cast(substr(codigo, 5) as integer)) as maximo')
            ->value('maximo');
        $codigo = CodigoDePdi::desdeNumero($ultimo + 1)->valor();

        $modelo = PdiModel::query()->create([
            'tenant_id' => $pdi->tenantId,
            'codigo' => $codigo,
            'colaborador_id' => $pdi->colaboradorId,
            'tipo' => $pdi->tipo->value,
            'created_by' => $actorId,
        ] + $this->columnasDeCabecera($pdi) + ['updated_by' => $actorId]);

        $this->insertarHijos($pdi, (int) $modelo->id, $actorId);
        $this->registrarHistorial($pdi->tenantId, (int) $modelo->id, TipoDeHistorialPdi::Creacion, null, null, $pdi->estado->value, null, $actorId);

        return $this->obtener($pdi->tenantId, (int) $modelo->id);
    }

    private function insertarHijos(Pdi $pdi, int $pdiId, int $actorId): void
    {
        foreach (array_values($pdi->compromisos) as $orden => $c) {
            $compromiso = PdiCompromisoModel::query()->create([
                'tenant_id' => $pdi->tenantId,
                'pdi_id' => $pdiId,
                'orden' => $orden + 1,
                'dimension' => $c->dimension->value,
                'tipo_elemento' => $c->tipoElemento,
                'elemento_id' => $c->elementoId,
                'descripcion' => $c->descripcion,
                'nivel_actual' => $c->nivelActual,
                'nivel_objetivo' => $c->nivelObjetivo,
                'indicador' => $c->indicador,
                'fecha_inicio' => $c->fechaInicio->format('Y-m-d'),
                'fecha_fin' => $c->fechaFin->format('Y-m-d'),
                'resultado' => $c->resultado?->value,
            ]);

            foreach (array_values($c->acciones) as $j => $a) {
                PdiAccionModel::query()->create([
                    'tenant_id' => $pdi->tenantId,
                    'compromiso_id' => $compromiso->id,
                    'orden' => $j + 1,
                    'tipo' => $a->tipo->value,
                    'descripcion' => $a->descripcion,
                    'persona_cargo' => $a->personaCargo,
                    'fecha_inicio' => $a->fechaInicio->format('Y-m-d'),
                    'fecha_fin' => $a->fechaFin->format('Y-m-d'),
                    'plan_accion_id' => $a->planAccionId,
                    'estado' => $a->estado->value,
                    'estado_congelado' => $a->estadoCongelado,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);
            }
        }

        foreach ($pdi->hitos as $h) {
            PdiHitoModel::query()->create([
                'tenant_id' => $pdi->tenantId,
                'pdi_id' => $pdiId,
                'fecha' => $h->fecha->format('Y-m-d'),
                'descripcion' => $h->descripcion,
                'estado' => $h->estado->value,
                'observaciones' => $h->observaciones,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function columnasDeCabecera(Pdi $pdi): array
    {
        return [
            'objetivo' => $pdi->objetivo,
            'puesto_objetivo_id' => $pdi->puestoObjetivoId,
            'origen_dnf_id' => $pdi->origenDnfId,
            'origen_adecuacion_id' => $pdi->origenAdecuacionId,
            'responsable_id' => $pdi->responsableId,
            'fecha_inicio' => $pdi->periodo->inicio->format('Y-m-d'),
            'fecha_fin' => $pdi->periodo->fin->format('Y-m-d'),
            'estado' => $pdi->estado->value,
            'version' => $pdi->version,
            'acuerdo_responsable_at' => $pdi->acuerdoResponsableAt?->format('Y-m-d H:i:s'),
            'acuerdo_responsable_by' => $pdi->acuerdoResponsableBy,
            'aceptacion_colaborador_at' => $pdi->aceptacionColaboradorAt?->format('Y-m-d H:i:s'),
            'comentario_colaborador' => $pdi->comentarioColaborador,
            'resultado' => $pdi->resultado?->value,
            'conclusion' => $pdi->conclusion,
            'cerrado_at' => $pdi->cerradoAt?->format('Y-m-d H:i:s'),
            'cerrado_by' => $pdi->cerradoBy,
        ];
    }

    private function aEntidad(PdiModel $m): Pdi
    {
        return new Pdi(
            (int) $m->id,
            (int) $m->tenant_id,
            (string) $m->codigo,
            (int) $m->colaborador_id,
            TipoDePdi::from($m->tipo),
            (string) $m->objetivo,
            $m->puesto_objetivo_id === null ? null : (int) $m->puesto_objetivo_id,
            $m->origen_dnf_id === null ? null : (int) $m->origen_dnf_id,
            $m->origen_adecuacion_id === null ? null : (int) $m->origen_adecuacion_id,
            (int) $m->responsable_id,
            new PeriodoDePdi($this->dia($m->fecha_inicio), $this->dia($m->fecha_fin)),
            EstadoDePdi::from($m->estado),
            (int) $m->version,
            $m->acuerdo_responsable_at === null ? null : $this->fecha($m->acuerdo_responsable_at),
            $m->acuerdo_responsable_by === null ? null : (int) $m->acuerdo_responsable_by,
            $m->aceptacion_colaborador_at === null ? null : $this->fecha($m->aceptacion_colaborador_at),
            $m->comentario_colaborador,
            $m->resultado === null ? null : ResultadoDePdi::from($m->resultado),
            $m->conclusion,
            $m->cerrado_at === null ? null : $this->fecha($m->cerrado_at),
            $m->cerrado_by === null ? null : (int) $m->cerrado_by,
            (int) $m->created_by,
            $m->created_at === null ? null : $this->fecha($m->created_at),
            $m->compromisos->map(fn (PdiCompromisoModel $c) => $this->aCompromiso($c))->values()->all(),
            $m->hitos->map(fn (PdiHitoModel $h) => $this->aHito($h))->values()->all(),
        );
    }

    private function aCompromiso(PdiCompromisoModel $c): CompromisoDePdi
    {
        return new CompromisoDePdi(
            (int) $c->id,
            (int) $c->orden,
            DimensionDeCompromiso::from($c->dimension),
            $c->tipo_elemento,
            $c->elemento_id === null ? null : (int) $c->elemento_id,
            $c->descripcion,
            $c->nivel_actual,
            $c->nivel_objetivo,
            $c->indicador,
            $this->dia($c->fecha_inicio),
            $this->dia($c->fecha_fin),
            $c->resultado === null ? null : ResultadoDeCompromiso::from($c->resultado),
            $c->acciones->map(fn (PdiAccionModel $a) => $this->aAccion($a))->values()->all(),
        );
    }

    private function aAccion(PdiAccionModel $a): AccionDePdi
    {
        return new AccionDePdi(
            (int) $a->id,
            (int) $a->orden,
            TipoDeAccionPdi::from($a->tipo),
            (string) $a->descripcion,
            $a->persona_cargo,
            $this->dia($a->fecha_inicio),
            $this->dia($a->fecha_fin),
            $a->plan_accion_id === null ? null : (int) $a->plan_accion_id,
            EstadoDeAccionPdi::from($a->estado),
            (bool) $a->estado_congelado,
        );
    }

    private function aHito(PdiHitoModel $h): HitoDePdi
    {
        return new HitoDePdi((int) $h->id, $this->dia($h->fecha), (string) $h->descripcion, EstadoDeHito::from($h->estado), $h->observaciones);
    }

    private function dia(DateTimeInterface $fecha): DateTimeImmutable
    {
        return new DateTimeImmutable($fecha->format('Y-m-d'));
    }

    private function fecha(DateTimeInterface $fecha): DateTimeImmutable
    {
        return new DateTimeImmutable($fecha->format('Y-m-d H:i:s'));
    }
}
