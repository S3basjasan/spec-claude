<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Desarrollo\Domain\Entities\Brecha;
use App\Modules\Desarrollo\Domain\Entities\Dnf;
use App\Modules\Desarrollo\Domain\Entities\ResultadoColaborador;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\AlcanceDnf;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroDnf;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Domain\ValueObjects\PaginaDeResumenes;
use App\Modules\Desarrollo\Domain\ValueObjects\PrioridadBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\ResumenDnf;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoCompetenciaDnf;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\DnfColaboradorModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\DnfDetalleModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\DnfModel;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class EloquentDnfRepository implements DnfRepositoryInterface
{
    private const INTENTOS_DE_CODIGO = 3;

    private const LOTE_DE_DETALLES = 200;

    private const RANGO_DE_PRIORIDAD = "CASE dd.prioridad WHEN 'Critica' THEN 4 WHEN 'Alta' THEN 3 WHEN 'Media' THEN 2 WHEN 'Baja' THEN 1 ELSE 0 END";

    public function guardar(Dnf $dnf): Dnf
    {
        for ($intento = 0; $intento < self::INTENTOS_DE_CODIGO; $intento++) {
            try {
                return DB::transaction(fn () => $this->insertar($dnf, $intento));
            } catch (UniqueConstraintViolationException $e) {
                if ($intento === self::INTENTOS_DE_CODIGO - 1) {
                    Log::warning('No se pudo asignar un código único de DNF.', ['tenant_id' => $dnf->tenantId]);
                    throw $e;
                }
            }
        }

        throw new \LogicException('No se pudo guardar la DNF.');
    }

    public function obtener(int $tenantId, int $id, ?int $colaboradorId = null): ?Dnf
    {
        $modelo = DnfModel::query()->where('tenant_id', $tenantId)->whereKey($id)->first();
        if ($modelo === null) {
            return null;
        }

        $colaboradores = DnfColaboradorModel::query()
            ->where('dnf_id', $modelo->id)
            ->when($colaboradorId !== null, fn ($q) => $q->where('colaborador_id', $colaboradorId))
            ->with('detalles')
            ->orderBy('id')
            ->get();

        if ($colaboradorId !== null && $colaboradores->isEmpty()) {
            return null;
        }

        return $this->aEntidad($modelo, $colaboradores->all());
    }

    public function listar(int $tenantId, FiltroDnf $filtro): PaginaDeResumenes
    {
        $rango = self::RANGO_DE_PRIORIDAD;
        $brechas = "SELECT COUNT(*) FROM dnf_detalles dd JOIN dnf_colaboradores dc ON dc.id = dd.dnf_colaborador_id WHERE dc.dnf_id = dnf.id AND dd.estado = 'ConBrecha'";
        $maximo = "SELECT COALESCE(MAX({$rango}), 0) FROM dnf_detalles dd JOIN dnf_colaboradores dc ON dc.id = dd.dnf_colaborador_id WHERE dc.dnf_id = dnf.id AND dd.estado = 'ConBrecha'";

        $consulta = DB::table('dnf')
            ->where('dnf.tenant_id', $tenantId)
            ->when($filtro->fechaDesde !== null, fn ($q) => $q->whereDate('dnf.created_at', '>=', $filtro->fechaDesde))
            ->when($filtro->fechaHasta !== null, fn ($q) => $q->whereDate('dnf.created_at', '<=', $filtro->fechaHasta))
            ->when($filtro->departamentoId !== null, fn ($q) => $q->where('dnf.departamento_id', $filtro->departamentoId))
            ->when($filtro->colaboradorId !== null, fn ($q) => $q->whereExists(
                fn ($sub) => $sub->select(DB::raw(1))->from('dnf_colaboradores as f')
                    ->whereColumn('f.dnf_id', 'dnf.id')->where('f.colaborador_id', $filtro->colaboradorId),
            ))
            ->when($filtro->prioridad !== null, fn ($q) => $q->whereRaw("({$maximo}) = ?", [$filtro->prioridad->orden()]));

        $total = (clone $consulta)->count();

        $filas = $consulta
            ->selectRaw("dnf.*, ({$brechas}) AS cantidad_brechas, ({$maximo}) AS rango_maximo, (SELECT COUNT(*) FROM dnf_colaboradores x WHERE x.dnf_id = dnf.id) AS colaboradores, (SELECT MIN(y.colaborador_id) FROM dnf_colaboradores y WHERE y.dnf_id = dnf.id) AS colaborador_unico")
            ->orderByDesc('dnf.created_at')->orderByDesc('dnf.id')
            ->forPage($filtro->pagina, $filtro->porPagina)
            ->get();

        $items = $filas->map(function (object $f): ResumenDnf {
            $alcance = AlcanceDnf::from($f->alcance);

            return new ResumenDnf(
                (int) $f->id,
                $f->codigo,
                new DateTimeImmutable($f->created_at),
                $alcance,
                $f->departamento_id === null ? null : (int) $f->departamento_id,
                $alcance === AlcanceDnf::Individual && $f->colaborador_unico !== null ? (int) $f->colaborador_unico : null,
                (int) $f->colaboradores,
                (int) $f->cantidad_brechas,
                $this->prioridadDeRango((int) $f->rango_maximo),
                $f->plan_formacion_id !== null,
            );
        })->all();

        return new PaginaDeResumenes($items, $total, $filtro->pagina, $filtro->porPagina);
    }

    public function masRecienteDeColaborador(int $tenantId, int $colaboradorId): ?Dnf
    {
        $id = DnfModel::query()
            ->where('tenant_id', $tenantId)
            ->whereExists(fn ($sub) => $sub->select(DB::raw(1))->from('dnf_colaboradores as c')
                ->whereColumn('c.dnf_id', 'dnf.id')->where('c.colaborador_id', $colaboradorId))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->value('id');

        return $id === null ? null : $this->obtener($tenantId, (int) $id, $colaboradorId);
    }

    public function asignarPlan(int $tenantId, int $dnfId, int $planId, int $actorId, DateTimeImmutable $momento): bool
    {
        $afectadas = DnfModel::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($dnfId)
            ->whereNull('plan_formacion_id')
            ->update([
                'plan_formacion_id' => $planId,
                'plan_generado_por' => $actorId,
                'plan_generado_en' => $momento->format('Y-m-d H:i:s'),
                'updated_at' => $momento->format('Y-m-d H:i:s'),
            ]);

        return $afectadas === 1;
    }

    private function insertar(Dnf $dnf, int $desplazamiento): Dnf
    {
        $numero = DnfModel::query()->where('tenant_id', $dnf->tenantId)->count() + 1 + $desplazamiento;
        $codigo = sprintf('DNF-%04d', $numero);

        $modelo = DnfModel::query()->create([
            'tenant_id' => $dnf->tenantId,
            'codigo' => $codigo,
            'alcance' => $dnf->alcance->value,
            'departamento_id' => $dnf->departamentoId,
            'competencias_omitidas' => $dnf->competenciasOmitidas,
            'created_by' => $dnf->creadoPor,
        ]);

        foreach ($dnf->resultados as $resultado) {
            $colaborador = DnfColaboradorModel::query()->create([
                'dnf_id' => $modelo->id,
                'colaborador_id' => $resultado->colaboradorId,
                'puesto_id' => $resultado->puestoId,
            ]);

            $filas = array_map(fn (Brecha $b) => [
                'dnf_colaborador_id' => $colaborador->id,
                'competencia_id' => $b->competenciaId,
                'tipo_competencia' => $b->tipo->value,
                'nivel_requerido' => $b->nivelRequerido->value,
                'nivel_actual' => $b->nivelActual?->value,
                'diferencia' => $b->diferencia,
                'brecha_porcentaje' => $b->brechaPorcentaje,
                'prioridad' => $b->prioridad?->value,
                'estado' => $b->estado->value,
            ], $resultado->brechas);

            foreach (array_chunk($filas, self::LOTE_DE_DETALLES) as $lote) {
                DnfDetalleModel::query()->insert($lote);
            }
        }

        return $dnf->guardada((int) $modelo->id, $codigo, new DateTimeImmutable((string) $modelo->created_at));
    }

    /**
     * @param  array<int, DnfColaboradorModel>  $colaboradores
     */
    private function aEntidad(DnfModel $modelo, array $colaboradores): Dnf
    {
        $resultados = array_map(fn (DnfColaboradorModel $c) => new ResultadoColaborador(
            (int) $c->colaborador_id,
            (int) $c->puesto_id,
            $c->detalles->sortBy('id')->map(fn (DnfDetalleModel $d) => new Brecha(
                (int) $d->competencia_id,
                TipoCompetenciaDnf::from($d->tipo_competencia),
                NivelGrado::from($d->nivel_requerido),
                $d->nivel_actual === null ? null : NivelGrado::from($d->nivel_actual),
                $d->diferencia === null ? null : (int) $d->diferencia,
                $d->brecha_porcentaje === null ? null : (float) $d->brecha_porcentaje,
                $d->prioridad === null ? null : PrioridadBrecha::from($d->prioridad),
                EstadoBrecha::from($d->estado),
            ))->values()->all(),
        ), $colaboradores);

        return new Dnf(
            (int) $modelo->id,
            (int) $modelo->tenant_id,
            $modelo->codigo,
            AlcanceDnf::from($modelo->alcance),
            $modelo->departamento_id === null ? null : (int) $modelo->departamento_id,
            (int) $modelo->competencias_omitidas,
            $modelo->plan_formacion_id === null ? null : (int) $modelo->plan_formacion_id,
            $modelo->plan_generado_por === null ? null : (int) $modelo->plan_generado_por,
            $modelo->plan_generado_en === null ? null : DateTimeImmutable::createFromInterface($modelo->plan_generado_en),
            (int) $modelo->created_by,
            DateTimeImmutable::createFromInterface($modelo->created_at),
            $resultados,
        );
    }

    private function prioridadDeRango(int $rango): ?PrioridadBrecha
    {
        foreach (PrioridadBrecha::cases() as $prioridad) {
            if ($prioridad->orden() === $rango) {
                return $prioridad;
            }
        }

        return null;
    }
}
