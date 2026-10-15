<?php

namespace Database\Seeders;

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoUnidadModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;

class UnidadesSeeder extends Seeder
{
    /**
     * Siembra doce unidades en cinco niveles (idempotente por nombre), una de ellas inactiva, y asigna unidad a
     * algunas posiciones ya sembradas por PuestosSeeder para probar la desactivación en cascada. Depende de
     * TiposUnidadSeeder y PuestosSeeder. Las unidades se crean con los modelos (sin historial); los códigos son
     * `UO-` + id, y con la base recién sembrada van de UO-001 a UO-012 en el orden de la lista (UO-008 es la inactiva).
     */
    public function run(): void
    {
        $creadoPor = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $creadoPor = $creadoPor === null ? null : (int) $creadoPor;
        $tipos = TipoUnidadModel::query()->pluck('id', 'nivel');
        $porNombre = [];

        foreach ($this->unidades() as [$nombre, $nivelDelTipo, $padre, $inactiva]) {
            $unidad = UnidadModel::query()->where('nombre', $nombre)->first();

            if ($unidad === null) {
                $unidad = UnidadModel::query()->create([
                    'nombre' => $nombre,
                    'tipo_unidad_id' => $tipos[$nivelDelTipo],
                    'unidad_padre_id' => $padre === null ? null : $porNombre[$padre]->id,
                    'estado' => $inactiva ? 'Inactiva' : 'Activa',
                    'fecha_creacion' => now()->toDateString(),
                    'fecha_supresion' => $inactiva ? now()->toDateString() : null,
                    'version' => 1,
                    'created_by' => $creadoPor,
                ]);
                $unidad->forceFill(['codigo' => UnidadModel::codigoDeId($unidad->id)])->save();
            }

            $porNombre[$nombre] = $unidad;
        }

        // Las 3 posiciones vigentes de «Jefe de talento humano» (2 Ocupadas y 1 Vacante): una Ocupada va a la Sección
        // Desarrollo Web (rechazo de la cascada sobre su rama) y la Ocupada y la Vacante restantes, a la Gerencia de
        // Talento Humano. Las Suprimidas de «Desarrollador de software» van a Desarrollo de Software y la de
        // «Asistente contable» a Capacitación (inactiva). Infraestructura (con Soporte) y Selección Técnica quedan sin
        // posiciones: ramas para probar la cascada y la desactivación simple.
        $this->asignar('Jefe de talento humano', 'Ocupada', 1, $porNombre['Desarrollo Web']);
        $this->asignar('Jefe de talento humano', 'Ocupada', 1, $porNombre['Gerencia de Talento Humano']);
        $this->asignar('Jefe de talento humano', 'Vacante', 1, $porNombre['Gerencia de Talento Humano']);
        $this->asignar('Desarrollador de software', 'Suprimida', 2, $porNombre['Desarrollo de Software']);
        $this->asignar('Asistente contable', 'Suprimida', 1, $porNombre['Capacitación']);
    }

    private function asignar(string $nombreDelPuesto, string $estado, int $cantidad, UnidadModel $unidad): void
    {
        $puesto = PuestoModel::query()->where('nombre_clave', PuestoModel::claveDeNombre($nombreDelPuesto))->first();
        if ($puesto === null) {
            return;
        }

        PosicionModel::query()
            ->where('puesto_id', $puesto->id)
            ->where('estado', $estado)
            ->whereNull('unidad_organizacional_id')
            ->orderBy('id')
            ->limit($cantidad)
            ->get()
            ->each(fn (PosicionModel $posicion) => $posicion->forceFill(['unidad_organizacional_id' => $unidad->id])->save());
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: string|null, 3: bool}> nombre, nivel del tipo, padre, inactiva
     */
    private function unidades(): array
    {
        return [
            ['Gerencia General', 1, null, false],
            ['Gerencia de Talento Humano', 2, 'Gerencia General', false],
            ['Gerencia de Tecnología', 2, 'Gerencia General', false],
            ['Selección y Desarrollo', 3, 'Gerencia de Talento Humano', false],
            ['Infraestructura', 3, 'Gerencia de Tecnología', false],
            ['Desarrollo de Software', 3, 'Gerencia de Tecnología', false],
            ['Reclutamiento', 4, 'Selección y Desarrollo', false],
            ['Capacitación', 4, 'Selección y Desarrollo', true],
            ['Soporte', 4, 'Infraestructura', false],
            ['Aplicaciones', 4, 'Desarrollo de Software', false],
            ['Selección Técnica', 5, 'Reclutamiento', false],
            ['Desarrollo Web', 5, 'Aplicaciones', false],
        ];
    }
}
