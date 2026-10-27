<?php

namespace Database\Seeders;

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use Illuminate\Database\Seeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos sintéticos de la feature 006 (GP-02): vínculos y asignaciones de prueba (idempotente).
 *
 * Depende de PersonalSeeder, PuestosSeeder y UnidadesSeeder. Puesto compartido PU-002 con tres posiciones del tenant 1
 * (A ocupada hoy, B libre, C con ingreso futuro) y una posición del tenant 2.
 */
class IngresosSeeder extends Seeder
{
    public function run(): void
    {
        $unidad = UnidadModel::query()->where('estado', 'Activa')->whereNotNull('unidad_padre_id')->orderBy('id')->first()
            ?? UnidadModel::query()->where('estado', 'Activa')->orderBy('id')->first();
        $puesto = PuestoModel::query()->where('codigo', 'PU-002')->first();
        $otroPuesto = PuestoModel::query()->where('codigo', 'PU-001')->first();

        if ($unidad === null || $puesto === null || $otroPuesto === null) {
            return;
        }

        $posiciones = PosicionModel::query()->where('puesto_id', $puesto->id)->orderBy('id')->limit(3)->get();
        if ($posiciones->count() < 3) {
            return;
        }

        foreach ($posiciones as $posicion) {
            $posicion->forceFill(['unidad_organizacional_id' => $unidad->id, 'tenant_id' => 1])->save();
        }
        [$a, $b, $c] = $posiciones->all();

        // Tras la regla de máxima autoridad única (posiciones), una posición raíz del tenant 2 puede chocar con la del
        // tenant 1; la posición del tenant 2 es solo de apoyo para pruebas de aislamiento, así que se omite si choca.
        try {
            $tenantDos = PosicionModel::query()->where('tenant_id', 2)->first()
                ?? PosicionModel::query()->create([
                    'puesto_id' => $otroPuesto->id,
                    'unidad_organizacional_id' => $unidad->id,
                    'estado' => 'Vacante',
                    'tenant_id' => 2,
                ]);
        } catch (UniqueConstraintViolationException) {
            $tenantDos = null;
        }

        $admin = (int) DB::table('users')->where('email', 'admin@hrms.test')->value('id');
        $hoy = now();

        // EMP-001: Activo desde hace un año en la posición A.
        $this->vinculo($this->ficha('0102030405', 1), 'indefinido', $hoy->copy()->subYear()->toDateString(), null, $a->id, null, $admin);
        // EMP-005: Desvinculada, con un vínculo cerrado hace seis meses (la posición B queda libre desde entonces).
        $this->vinculo($this->ficha('0000123456', 1), 'temporal', $hoy->copy()->subYears(2)->toDateString(), $hoy->copy()->subMonths(6)->toDateString(), $b->id, $hoy->copy()->subMonths(6)->toDateString(), $admin);
        // EMP-004: ingreso programado dentro de 30 días en la posición C.
        $this->vinculo($this->ficha('0102-03', 1), 'plazo_fijo', $hoy->copy()->addDays(30)->toDateString(), null, $c->id, null, $admin);
        // La posición del tenant 2 queda libre para probar el aislamiento.
        unset($tenantDos);
    }

    private function ficha(string $numero, int $tenantId): ?int
    {
        $id = ColaboradorModel::query()->where('tenant_id', $tenantId)->where('numero_identificacion', $numero)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function vinculo(?int $fichaId, string $contrato, string $ingreso, ?string $salida, int $posicionId, ?string $fin, int $actor): void
    {
        if ($fichaId === null || DB::table('vinculos_laborales')->where('colaborador_id', $fichaId)->exists()) {
            return;
        }

        $ahora = now();
        $vinculoId = DB::table('vinculos_laborales')->insertGetId([
            'tenant_id' => 1,
            'colaborador_id' => $fichaId,
            'fecha_ingreso' => $ingreso,
            'fecha_salida' => $salida,
            'tipo_contrato_clave' => $contrato,
            'version' => 1,
            'operacion_id' => (string) Str::uuid(),
            'operacion_hash' => hash('sha256', "seed-$fichaId-$ingreso"),
            'created_by' => $actor,
            'updated_by' => $actor,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        DB::table('asignaciones_laborales')->insert([
            'tenant_id' => 1,
            'vinculo_id' => $vinculoId,
            'posicion_id' => $posicionId,
            'fecha_inicio' => $ingreso,
            'fecha_fin' => $fin,
            'version' => 1,
            'created_by' => $actor,
            'updated_by' => $actor,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }
}
