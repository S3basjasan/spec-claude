<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Desarrollo\Domain\Entities\EncuestaSatisfaccion;
use App\Modules\Desarrollo\Domain\Entities\ImpactoAccion;
use App\Modules\Desarrollo\Domain\Entities\SeguimientoImpacto;
use App\Modules\Desarrollo\Domain\Exceptions\EncuestaNoPermitida;
use App\Modules\Desarrollo\Domain\Exceptions\SeguimientoNoPermitido;
use App\Modules\Desarrollo\Domain\Repositories\ReportesRepositoryInterface;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\EncuestaSatisfaccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ImpactoAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\SeguimientoImpactoModel;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentReportesRepository implements ReportesRepositoryInterface
{
    public function registrarEncuesta(EncuestaSatisfaccion $encuesta): EncuestaSatisfaccion
    {
        try {
            $m = EncuestaSatisfaccionModel::query()->create([
                'tenant_id' => $encuesta->tenantId,
                'actividad_id' => $encuesta->actividadId,
                'colaborador_id' => $encuesta->colaboradorId,
                'calificacion' => $encuesta->calificacion,
                'comentario' => $encuesta->comentario,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new EncuestaNoPermitida(EncuestaNoPermitida::YA_RESPONDIDA);
        }

        return $this->encuesta($m);
    }

    public function encuestasDe(int $tenantId, ?array $actividadIds = null): array
    {
        if ($actividadIds !== null && $actividadIds === []) {
            return [];
        }

        return EncuestaSatisfaccionModel::query()->where('tenant_id', $tenantId)
            ->when($actividadIds !== null, fn ($q) => $q->whereIn('actividad_id', $actividadIds))
            ->orderBy('id')->get()->map(fn (EncuestaSatisfaccionModel $m) => $this->encuesta($m))->all();
    }

    public function encuestasDeColaborador(int $tenantId, int $colaboradorId): array
    {
        return EncuestaSatisfaccionModel::query()->where('tenant_id', $tenantId)->where('colaborador_id', $colaboradorId)
            ->orderBy('id')->get()->map(fn (EncuestaSatisfaccionModel $m) => $this->encuesta($m))->all();
    }

    public function encuestaDe(int $actividadId, int $colaboradorId): ?EncuestaSatisfaccion
    {
        $m = EncuestaSatisfaccionModel::query()->where('actividad_id', $actividadId)->where('colaborador_id', $colaboradorId)->first();

        return $m === null ? null : $this->encuesta($m);
    }

    public function comentarios(int $tenantId, int $actividadId, int $pagina, int $porPagina): array
    {
        $consulta = fn () => DB::table('encuestas_satisfaccion')
            ->where('tenant_id', $tenantId)->where('actividad_id', $actividadId)
            ->whereNotNull('comentario')->where('comentario', '<>', '');

        $items = $consulta()->orderByDesc('created_at')->orderByDesc('id')->forPage($pagina, $porPagina)
            ->get(['calificacion', 'comentario', 'created_at'])
            ->map(fn (object $f) => [
                'calificacion' => (int) $f->calificacion,
                'comentario' => (string) $f->comentario,
                'fecha' => substr((string) $f->created_at, 0, 10),
            ])->all();

        return ['items' => $items, 'total' => $consulta()->count()];
    }

    public function registrarSeguimiento(SeguimientoImpacto $seguimiento): SeguimientoImpacto
    {
        try {
            $m = SeguimientoImpactoModel::query()->create([
                'tenant_id' => $seguimiento->tenantId,
                'actividad_id' => $seguimiento->actividadId,
                'colaborador_id' => $seguimiento->colaboradorId,
                'hito' => $seguimiento->hito,
                'aplicacion' => $seguimiento->aplicacion,
                'apoyo' => $seguimiento->apoyo,
                'comentario' => $seguimiento->comentario,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new SeguimientoNoPermitido(SeguimientoNoPermitido::YA_RESPONDIDA);
        }

        return $this->seguimiento($m);
    }

    public function seguimientosDe(int $tenantId, ?array $actividadIds = null): array
    {
        if ($actividadIds !== null && $actividadIds === []) {
            return [];
        }

        return SeguimientoImpactoModel::query()->where('tenant_id', $tenantId)
            ->when($actividadIds !== null, fn ($q) => $q->whereIn('actividad_id', $actividadIds))
            ->orderBy('id')->get()->map(fn (SeguimientoImpactoModel $m) => $this->seguimiento($m))->all();
    }

    public function seguimientosDeColaborador(int $tenantId, int $colaboradorId): array
    {
        return SeguimientoImpactoModel::query()->where('tenant_id', $tenantId)->where('colaborador_id', $colaboradorId)
            ->orderBy('id')->get()->map(fn (SeguimientoImpactoModel $m) => $this->seguimiento($m))->all();
    }

    public function seguimientoDe(int $actividadId, int $colaboradorId, int $hito): ?SeguimientoImpacto
    {
        $m = SeguimientoImpactoModel::query()->where('actividad_id', $actividadId)->where('colaborador_id', $colaboradorId)->where('hito', $hito)->first();

        return $m === null ? null : $this->seguimiento($m);
    }

    public function impactoDe(int $tenantId, int $planAccionId): ?ImpactoAccion
    {
        $m = ImpactoAccionModel::query()->where('tenant_id', $tenantId)->where('plan_accion_id', $planAccionId)->first();

        return $m === null ? null : $this->impacto($m);
    }

    public function impactosDe(int $tenantId, ?array $planAccionIds = null): array
    {
        if ($planAccionIds !== null && $planAccionIds === []) {
            return [];
        }

        $salida = [];
        foreach (ImpactoAccionModel::query()->where('tenant_id', $tenantId)
            ->when($planAccionIds !== null, fn ($q) => $q->whereIn('plan_accion_id', $planAccionIds))->orderBy('id')->get() as $m) {
            $salida[(int) $m->plan_accion_id] = $this->impacto($m);
        }

        return $salida;
    }

    public function guardarImpacto(ImpactoAccion $impacto, int $usuarioId): ImpactoAccion
    {
        return DB::transaction(function () use ($impacto, $usuarioId) {
            $m = ImpactoAccionModel::query()->where('tenant_id', $impacto->tenantId)->where('plan_accion_id', $impacto->planAccionId)->lockForUpdate()->first();
            $datos = [
                'indicador' => $impacto->indicador,
                'unidad' => $impacto->unidad,
                'valor_antes' => $impacto->valorAntes,
                'valor_despues' => $impacto->valorDespues,
                'beneficio_estimado' => $impacto->beneficioEstimado,
                'justificacion' => $impacto->justificacion,
                'updated_by' => $usuarioId,
            ];
            if ($m === null) {
                $m = ImpactoAccionModel::query()->create($datos + [
                    'tenant_id' => $impacto->tenantId,
                    'plan_accion_id' => $impacto->planAccionId,
                    'created_by' => $usuarioId,
                ]);
            } else {
                $m->update($datos);
            }

            return $this->impacto($m->refresh());
        });
    }

    private function encuesta(EncuestaSatisfaccionModel $m): EncuestaSatisfaccion
    {
        return new EncuestaSatisfaccion(
            (int) $m->id, (int) $m->tenant_id, (int) $m->actividad_id, (int) $m->colaborador_id, (int) $m->calificacion,
            $m->comentario, $m->created_at === null ? null : new DateTimeImmutable($m->created_at->toDateTimeString()),
        );
    }

    private function seguimiento(SeguimientoImpactoModel $m): SeguimientoImpacto
    {
        return new SeguimientoImpacto(
            (int) $m->id, (int) $m->tenant_id, (int) $m->actividad_id, (int) $m->colaborador_id, (int) $m->hito,
            (int) $m->aplicacion, (int) $m->apoyo, $m->comentario,
            $m->created_at === null ? null : new DateTimeImmutable($m->created_at->toDateTimeString()),
        );
    }

    private function impacto(ImpactoAccionModel $m): ImpactoAccion
    {
        return new ImpactoAccion(
            (int) $m->id, (int) $m->tenant_id, (int) $m->plan_accion_id, (string) $m->indicador, (string) $m->unidad,
            (float) $m->valor_antes, (float) $m->valor_despues, (float) $m->beneficio_estimado, (string) $m->justificacion,
            (int) $m->created_by, (int) $m->updated_by,
            $m->created_at === null ? null : new DateTimeImmutable($m->created_at->toDateTimeString()),
            $m->updated_at === null ? null : new DateTimeImmutable($m->updated_at->toDateTimeString()),
        );
    }
}
