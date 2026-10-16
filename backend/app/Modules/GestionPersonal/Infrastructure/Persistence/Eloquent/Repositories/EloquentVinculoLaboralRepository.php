<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\AsignacionLaboral;
use App\Modules\GestionPersonal\Domain\Entities\VinculoLaboral;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;
use App\Modules\GestionPersonal\Domain\Exceptions\PosicionNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\VinculoSuperpuesto;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class EloquentVinculoLaboralRepository implements VinculoLaboralRepositoryInterface
{
    private const REINTENTOS_DE_BLOQUEO = 3;

    public function vinculosDeFicha(int $tenantId, int $colaboradorId): array
    {
        return $this->vinculosDeFichas($tenantId, [$colaboradorId])[$colaboradorId] ?? [];
    }

    public function vinculosDeFichas(int $tenantId, array $colaboradorIds): array
    {
        if ($colaboradorIds === []) {
            return [];
        }

        $filas = DB::table('vinculos_laborales')
            ->where('tenant_id', $tenantId)
            ->whereIn('colaborador_id', array_values(array_unique($colaboradorIds)))
            ->orderBy('fecha_ingreso')->orderBy('id')
            ->get();

        return $this->hidratar($filas);
    }

    public function vinculosDelTenant(int $tenantId): array
    {
        $filas = DB::table('vinculos_laborales')->where('tenant_id', $tenantId)
            ->orderBy('fecha_ingreso')->orderBy('id')->get();

        return $this->hidratar($filas);
    }

    public function asignacionesDePosicionDesde(int $posicionId, string $desde): array
    {
        return DB::table('asignaciones_laborales')
            ->where('posicion_id', $posicionId)
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>', $desde))
            ->orderBy('fecha_inicio')
            ->get()
            ->map(fn ($fila) => $this->aAsignacion($fila))
            ->all();
    }

    public function posicionesConAsignacionVigenteOFutura(array $posicionIds, string $fecha): array
    {
        if ($posicionIds === []) {
            return [];
        }

        return DB::table('asignaciones_laborales')
            ->whereIn('posicion_id', array_values(array_unique($posicionIds)))
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>', $fecha))
            ->distinct()
            ->pluck('posicion_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function fichasEnPosiciones(int $tenantId, array $posicionIds, string $fecha): array
    {
        if ($posicionIds === []) {
            return [];
        }

        return DB::table('asignaciones_laborales as a')
            ->join('vinculos_laborales as v', 'v.id', '=', 'a.vinculo_id')
            ->where('a.tenant_id', $tenantId)
            ->whereIn('a.posicion_id', array_values(array_unique($posicionIds)))
            ->where('a.fecha_inicio', '<=', $fecha)
            ->where(fn ($q) => $q->whereNull('a.fecha_fin')->orWhere('a.fecha_fin', '>', $fecha))
            ->distinct()
            ->pluck('v.colaborador_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function porOperacion(int $tenantId, string $operacionId): ?array
    {
        $fila = DB::table('vinculos_laborales')->where('tenant_id', $tenantId)->where('operacion_id', $operacionId)->first();
        if ($fila === null) {
            return null;
        }

        $vinculo = $this->hidratar(collect([$fila]))[(int) $fila->colaborador_id][0];

        return ['vinculo' => $vinculo, 'hash' => (string) $fila->operacion_hash, 'actor' => (int) $fila->created_by];
    }

    public function ejecutarBajoBloqueo(int $tenantId, int $colaboradorId, callable $accion): mixed
    {
        $intentos = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($tenantId, $colaboradorId, $accion) {
                    // Primera instrucción: una escritura. En SQLite toma el bloqueo del único escritor antes de leer.
                    DB::table('colaboradores')->where('id', $colaboradorId)->where('tenant_id', $tenantId)
                        ->update(['updated_at' => DB::raw('updated_at')]);

                    return $accion();
                });
            } catch (QueryException $e) {
                if (! $this->esBloqueo($e)) {
                    throw $e;
                }
                if (++$intentos > self::REINTENTOS_DE_BLOQUEO) {
                    throw new IngresoNoVerificable;
                }
                usleep(random_int(50_000, 200_000));
            }
        }
    }

    public function insertarIngreso(
        int $tenantId,
        int $colaboradorId,
        string $operacionId,
        string $hash,
        string $fechaIngreso,
        string $tipoContratoClave,
        int $posicionId,
        int $actorId,
    ): VinculoLaboral {
        $ahora = now();

        try {
            $vinculoId = DB::table('vinculos_laborales')->insertGetId([
                'tenant_id' => $tenantId,
                'colaborador_id' => $colaboradorId,
                'fecha_ingreso' => $fechaIngreso,
                'fecha_salida' => null,
                'tipo_contrato_clave' => $tipoContratoClave,
                'version' => 1,
                'operacion_id' => $operacionId,
                'operacion_hash' => $hash,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            $asignacionId = DB::table('asignaciones_laborales')->insertGetId([
                'tenant_id' => $tenantId,
                'vinculo_id' => $vinculoId,
                'posicion_id' => $posicionId,
                'fecha_inicio' => $fechaIngreso,
                'fecha_fin' => null,
                'version' => 1,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw $this->traducirUnicidad($e);
        } catch (QueryException $e) {
            throw $this->traducirTrigger($e) ?? $e;
        }

        return new VinculoLaboral(
            $vinculoId,
            $tenantId,
            $colaboradorId,
            $fechaIngreso,
            null,
            $tipoContratoClave,
            1,
            $actorId,
            $ahora->toIso8601ZuluString(),
            [new AsignacionLaboral($asignacionId, $vinculoId, $posicionId, $fechaIngreso, null)],
        );
    }

    // ------------------------------------------------------------------ Apoyo

    /**
     * @param  Collection<int, object>  $filas
     * @return array<int, list<VinculoLaboral>>
     */
    private function hidratar(Collection $filas): array
    {
        if ($filas->isEmpty()) {
            return [];
        }

        $asignaciones = DB::table('asignaciones_laborales')
            ->whereIn('vinculo_id', $filas->pluck('id')->all())
            ->orderBy('fecha_inicio')
            ->get()
            ->groupBy('vinculo_id');

        $salida = [];
        foreach ($filas as $fila) {
            $propias = ($asignaciones[$fila->id] ?? collect())->map(fn ($a) => $this->aAsignacion($a))->values()->all();
            $salida[(int) $fila->colaborador_id][] = new VinculoLaboral(
                (int) $fila->id,
                (int) $fila->tenant_id,
                (int) $fila->colaborador_id,
                (string) $fila->fecha_ingreso,
                $fila->fecha_salida === null ? null : (string) $fila->fecha_salida,
                (string) $fila->tipo_contrato_clave,
                (int) $fila->version,
                (int) $fila->created_by,
                Carbon::parse($fila->created_at)->toIso8601ZuluString(),
                $propias,
            );
        }

        return $salida;
    }

    private function aAsignacion(object $fila): AsignacionLaboral
    {
        return new AsignacionLaboral(
            (int) $fila->id,
            (int) $fila->vinculo_id,
            (int) $fila->posicion_id,
            (string) $fila->fecha_inicio,
            $fila->fecha_fin === null ? null : (string) $fila->fecha_fin,
        );
    }

    private function esBloqueo(QueryException $e): bool
    {
        $mensaje = strtolower($e->getMessage());

        return str_contains($mensaje, 'database is locked') || str_contains($mensaje, 'database table is locked')
            || str_contains($mensaje, 'busy');
    }

    private function traducirTrigger(QueryException $e): ?Throwable
    {
        $mensaje = $e->getMessage();

        if (str_contains($mensaje, 'VINCULO_SUPERPUESTO') || str_contains($mensaje, 'ASIGNACION_SUPERPUESTA')) {
            return new VinculoSuperpuesto;
        }
        if (str_contains($mensaje, 'POSICION_SUPERPUESTA')) {
            return new PosicionNoDisponible;
        }

        return null;
    }

    private function traducirUnicidad(UniqueConstraintViolationException $e): Throwable
    {
        $mensaje = $e->getMessage();

        if (str_contains($mensaje, 'vinculos_laborales.colaborador_id')) {
            return new VinculoSuperpuesto;
        }
        if (str_contains($mensaje, 'asignaciones_laborales.posicion_id')) {
            return new PosicionNoDisponible;
        }
        if (str_contains($mensaje, 'asignaciones_laborales.vinculo_id')) {
            return new VinculoSuperpuesto;
        }

        return new ConflictoDeUnicidad;
    }
}
