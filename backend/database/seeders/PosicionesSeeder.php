<?php

namespace Database\Seeders;

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PosicionesSeeder extends Seeder
{
    /**
     * Completa las seis posiciones que ya siembran PuestosSeeder y UnidadesSeeder (código, modalidad, supervisor y
     * ocupantes) y añade cuatro Vacantes para reproducir una estructura de supervisión sobre las doce unidades:
     *
     * - Máxima autoridad (Vacante, sin supervisor) en la Gerencia General (UO-001).
     * - Las dos Ocupadas existentes con los usuarios de prueba: una en Desarrollo Web (UO-012) y otra en la Gerencia de
     *   Talento Humano (UO-002); esta última supervisa a una Vacante de Selección y Desarrollo (UO-004).
     * - Vacante sin subordinadas en UO-002; Vacantes con subordinadas en UO-001, UO-003 y UO-010; una Vacante en UO-012
     *   que reporta a la Ocupada de su misma unidad (supervisión dentro de una unidad).
     * - Las Suprimidas (sin ocupante ni subordinadas) en UO-006 y UO-008.
     * - Sin posiciones vigentes en UO-005, UO-008, UO-009 ni UO-011; las cuatro modalidades.
     *
     * Idempotente: no hace nada si la Gerencia General ya tiene posiciones. Depende de UsuariosInicialesSeeder,
     * PuestosSeeder y UnidadesSeeder. Escribe por Eloquent (sin historial), como los demás seeders de la estructura.
     */
    public function run(): void
    {
        $this->asignarCodigos();

        $unidades = UnidadModel::query()->pluck('id', 'codigo');
        $raiz = $unidades['UO-001'] ?? null;
        if ($raiz === null || PosicionModel::query()->where('unidad_organizacional_id', $raiz)->exists()) {
            return;
        }

        $admin = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $usuario = UserModel::query()->where('email', 'usuario@hrms.test')->value('id');
        $actor = $admin === null ? null : (int) $admin;

        $ocupadas = PosicionModel::query()->where('estado', 'Ocupada')->orderBy('id')->get();
        $vacante = PosicionModel::query()->where('estado', 'Vacante')->orderBy('id')->first();
        $suprimidas = PosicionModel::query()->where('estado', 'Suprimida')->orderBy('id')->get();
        if ($ocupadas->count() < 2 || $vacante === null) {
            return;
        }
        [$enDesarrolloWeb, $enTalentoHumano] = [$ocupadas[0], $ocupadas[1]];

        DB::transaction(function () use ($unidades, $actor, $admin, $usuario, $enDesarrolloWeb, $enTalentoHumano, $vacante, $suprimidas) {
            $comercial = $this->puesto('Ejecutivo de gestión comercial');
            $analista = $this->puesto('Analista de datos');
            $desarrollador = $this->puesto('Desarrollador de software');

            // La máxima autoridad se crea colgada de la antigua raíz y se promueve cuando esta ya tiene otro supervisor:
            // nunca hay dos posiciones vigentes sin supervisor a la vez.
            $maxima = $this->crear($comercial, $unidades['UO-001'], $enDesarrolloWeb->id, 'Nombramiento', $actor);
            $enDesarrolloWeb->forceFill(['posicion_jefe_id' => $maxima->id])->save();
            $maxima->forceFill(['posicion_jefe_id' => null])->save();

            $tecnologia = $this->crear($analista, $unidades['UO-003'], $maxima->id, 'Encargo', $actor);
            $aplicaciones = $this->crear($desarrollador, $unidades['UO-010'], $tecnologia->id, 'Subrogacion', $actor);
            $this->crear($analista, $unidades['UO-004'], $enTalentoHumano->id, 'Contrato', $actor);
            // Supervisión dentro de una misma unidad: un Desarrollador que reporta a la Ocupada de Desarrollo Web.
            $this->crear($desarrollador, $unidades['UO-012'], $enDesarrolloWeb->id, 'Contrato', $actor);

            $enTalentoHumano->forceFill([
                'posicion_jefe_id' => $maxima->id, 'modalidad' => 'Nombramiento', 'user_id' => $admin, 'created_by' => $actor,
            ])->save();
            $vacante->forceFill([
                'posicion_jefe_id' => $maxima->id, 'modalidad' => 'Contrato', 'created_by' => $actor,
            ])->save();
            $enDesarrolloWeb->forceFill([
                'posicion_jefe_id' => $aplicaciones->id, 'modalidad' => 'Nombramiento', 'user_id' => $usuario, 'created_by' => $actor,
            ])->save();

            foreach ($suprimidas as $suprimida) {
                $suprimida->forceFill(['modalidad' => 'Contrato', 'created_by' => $actor])->save();
            }

        });
    }

    /** Código `POS-####` a las posiciones que ya existían sin él. */
    private function asignarCodigos(): void
    {
        PosicionModel::query()->whereNull('codigo')->orderBy('id')->get()
            ->each(fn (PosicionModel $posicion) => $posicion->forceFill(['codigo' => PosicionModel::codigoDeId($posicion->id)])->save());
    }

    private function puesto(string $nombre): int
    {
        return (int) PuestoModel::query()->where('nombre_clave', PuestoModel::claveDeNombre($nombre))->value('id');
    }

    private function crear(int $puestoId, int $unidadId, int $jefeId, string $modalidad, ?int $actor): PosicionModel
    {
        $posicion = PosicionModel::query()->create([
            'puesto_id' => $puestoId,
            'unidad_organizacional_id' => $unidadId,
            'posicion_jefe_id' => $jefeId,
            'estado' => 'Vacante',
            'modalidad' => $modalidad,
            'version' => 1,
            'created_by' => $actor,
        ]);
        $posicion->forceFill(['codigo' => PosicionModel::codigoDeId($posicion->id)])->save();

        return $posicion;
    }
}
