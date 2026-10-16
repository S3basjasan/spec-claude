<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\ExperienciaAnterior;
use App\Modules\GestionPersonal\Domain\Entities\FormacionAcademica;
use App\Modules\GestionPersonal\Domain\Entities\Movilidad;
use App\Modules\GestionPersonal\Domain\Exceptions\RegistroModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\RegistroNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\DesarrolloRepositoryInterface;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ExperienciaAnteriorModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\FormacionAcademicaModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EloquentDesarrolloRepository implements DesarrolloRepositoryInterface
{
    public function formaciones(int $tenantId, int $colaboradorId): array
    {
        return $this->consultaFormaciones($tenantId)
            ->where('colaborador_id', $colaboradorId)
            ->get()
            ->map(fn (FormacionAcademicaModel $m) => $this->aFormacion($m))
            ->all();
    }

    public function experiencias(int $tenantId, int $colaboradorId): array
    {
        return $this->consultaExperiencias($tenantId)
            ->where('colaborador_id', $colaboradorId)
            ->get()
            ->map(fn (ExperienciaAnteriorModel $m) => $this->aExperiencia($m))
            ->all();
    }

    public function movilidad(int $tenantId, int $colaboradorId): ?Movilidad
    {
        $modelo = ColaboradorModel::query()->where('tenant_id', $tenantId)->whereKey($colaboradorId)->first();

        return $modelo === null ? null : $this->aMovilidad($modelo);
    }

    public function formacion(int $tenantId, int $colaboradorId, int $id): ?FormacionAcademica
    {
        $modelo = FormacionAcademicaModel::query()
            ->where('tenant_id', $tenantId)->where('colaborador_id', $colaboradorId)->whereKey($id)->first();

        return $modelo === null ? null : $this->aFormacion($modelo);
    }

    public function experiencia(int $tenantId, int $colaboradorId, int $id): ?ExperienciaAnterior
    {
        $modelo = ExperienciaAnteriorModel::query()
            ->where('tenant_id', $tenantId)->where('colaborador_id', $colaboradorId)->whereKey($id)->first();

        return $modelo === null ? null : $this->aExperiencia($modelo);
    }

    public function crearFormacion(int $tenantId, int $colaboradorId, int $actorId, array $datos): FormacionAcademica
    {
        $modelo = FormacionAcademicaModel::query()->create(array_merge($datos, [
            'tenant_id' => $tenantId,
            'colaborador_id' => $colaboradorId,
            'version' => 1,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]));

        return $this->aFormacion($modelo->refresh());
    }

    public function crearExperiencia(int $tenantId, int $colaboradorId, int $actorId, array $datos): ExperienciaAnterior
    {
        $modelo = ExperienciaAnteriorModel::query()->create(array_merge($datos, [
            'tenant_id' => $tenantId,
            'colaborador_id' => $colaboradorId,
            'version' => 1,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]));

        return $this->aExperiencia($modelo->refresh());
    }

    public function actualizarFormacion(int $tenantId, int $colaboradorId, int $id, int $version, int $actorId, array $cambios): FormacionAcademica
    {
        $filas = FormacionAcademicaModel::query()
            ->where('tenant_id', $tenantId)->where('colaborador_id', $colaboradorId)
            ->whereKey($id)->where('version', $version)
            ->update(array_merge($cambios, [
                'version' => DB::raw('version + 1'),
                'updated_by' => $actorId,
                'updated_at' => now(),
            ]));

        if ($filas === 0) {
            throw $this->formacion($tenantId, $colaboradorId, $id) === null ? new RegistroNoEncontrado : new RegistroModificado;
        }

        return $this->formacion($tenantId, $colaboradorId, $id) ?? throw new RegistroNoEncontrado;
    }

    public function actualizarExperiencia(int $tenantId, int $colaboradorId, int $id, int $version, int $actorId, array $cambios): ExperienciaAnterior
    {
        $filas = ExperienciaAnteriorModel::query()
            ->where('tenant_id', $tenantId)->where('colaborador_id', $colaboradorId)
            ->whereKey($id)->where('version', $version)
            ->update(array_merge($cambios, [
                'version' => DB::raw('version + 1'),
                'updated_by' => $actorId,
                'updated_at' => now(),
            ]));

        if ($filas === 0) {
            throw $this->experiencia($tenantId, $colaboradorId, $id) === null ? new RegistroNoEncontrado : new RegistroModificado;
        }

        return $this->experiencia($tenantId, $colaboradorId, $id) ?? throw new RegistroNoEncontrado;
    }

    public function actualizarMovilidad(int $tenantId, int $colaboradorId, int $version, int $actorId, array $cambios): Movilidad
    {
        // Query builder directo: no debe tocar `version`, `updated_by` ni `updated_at` de la ficha.
        $filas = DB::table('colaboradores')
            ->where('tenant_id', $tenantId)->where('id', $colaboradorId)->where('movilidad_version', $version)
            ->update([
                'movilidad_estado' => $cambios['estado'],
                'movilidad_observaciones' => $cambios['observaciones'],
                'movilidad_actualizada_at' => now(),
                'movilidad_actualizada_por' => $actorId,
                'movilidad_version' => DB::raw('movilidad_version + 1'),
            ]);

        if ($filas === 0) {
            throw $this->movilidad($tenantId, $colaboradorId) === null ? new RegistroNoEncontrado : new RegistroModificado;
        }

        return $this->movilidad($tenantId, $colaboradorId) ?? throw new RegistroNoEncontrado;
    }

    public function deColaboradores(int $tenantId, array $colaboradorIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $colaboradorIds)));
        if ($ids === []) {
            return [];
        }

        $salida = [];
        $fichas = ColaboradorModel::query()->where('tenant_id', $tenantId)->whereIn('id', $ids)->get();
        foreach ($fichas as $ficha) {
            $salida[(int) $ficha->id] = ['formaciones' => [], 'experiencias' => [], 'movilidad' => $this->aMovilidad($ficha)];
        }

        $this->consultaFormaciones($tenantId)->whereIn('colaborador_id', array_keys($salida))->get()
            ->each(function (FormacionAcademicaModel $m) use (&$salida) {
                $salida[(int) $m->colaborador_id]['formaciones'][] = $this->aFormacion($m);
            });
        $this->consultaExperiencias($tenantId)->whereIn('colaborador_id', array_keys($salida))->get()
            ->each(function (ExperienciaAnteriorModel $m) use (&$salida) {
                $salida[(int) $m->colaborador_id]['experiencias'][] = $this->aExperiencia($m);
            });

        return $salida;
    }

    /** @return Builder<FormacionAcademicaModel> */
    private function consultaFormaciones(int $tenantId): Builder
    {
        return FormacionAcademicaModel::query()
            ->where('tenant_id', $tenantId)
            ->orderByRaw('fecha_inicio IS NULL')
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id');
    }

    /** @return Builder<ExperienciaAnteriorModel> */
    private function consultaExperiencias(int $tenantId): Builder
    {
        return ExperienciaAnteriorModel::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id');
    }

    private function aFormacion(FormacionAcademicaModel $m): FormacionAcademica
    {
        return new FormacionAcademica(
            (int) $m->id,
            (int) $m->colaborador_id,
            (string) $m->nivel,
            (string) $m->titulo_programa,
            (string) $m->institucion,
            (string) $m->estado,
            $m->fecha_inicio,
            $m->fecha_finalizacion,
            $m->observaciones,
            (int) $m->version,
            (int) $m->created_by,
            (int) $m->updated_by,
            $m->created_at->toIso8601ZuluString(),
            $m->updated_at->toIso8601ZuluString(),
        );
    }

    private function aExperiencia(ExperienciaAnteriorModel $m): ExperienciaAnterior
    {
        return new ExperienciaAnterior(
            (int) $m->id,
            (int) $m->colaborador_id,
            (string) $m->organizacion,
            (string) $m->cargo,
            (string) $m->fecha_inicio,
            $m->fecha_finalizacion,
            $m->funciones,
            $m->observaciones,
            (int) $m->version,
            (int) $m->created_by,
            (int) $m->updated_by,
            $m->created_at->toIso8601ZuluString(),
            $m->updated_at->toIso8601ZuluString(),
        );
    }

    private function aMovilidad(ColaboradorModel $m): Movilidad
    {
        $actualizada = $m->getAttribute('movilidad_actualizada_at');

        return new Movilidad(
            (int) $m->id,
            (string) $m->getAttribute('movilidad_estado'),
            $m->getAttribute('movilidad_observaciones'),
            (int) $m->getAttribute('movilidad_version'),
            $actualizada === null ? null : Carbon::parse($actualizada, 'UTC')->toIso8601ZuluString(),
            $m->getAttribute('movilidad_actualizada_por') === null ? null : (int) $m->getAttribute('movilidad_actualizada_por'),
        );
    }
}
