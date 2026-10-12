<?php

namespace Database\Seeders;

use App\Modules\Estructura\Domain\Repositories\PreguntaRepositoryInterface;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PreguntasSeeder extends Seeder
{
    /**
     * Siembra cinco registros de preguntas (idempotente por competencia), uno de ellos de una competencia
     * inactiva. Depende de CompetenciasSeeder; las demás competencias activas quedan sin registro para poder
     * probar la creación. Pasa por el repositorio como la aplicación.
     */
    public function run(): void
    {
        $repositorio = app(PreguntaRepositoryInterface::class);
        $actorId = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $actorId = $actorId === null ? null : (int) $actorId;

        foreach ($this->registros() as $nombre => $preguntas) {
            $competencia = CompetenciaModel::query()->where('nombre_clave', CompetenciaModel::claveDeNombre($nombre))->first();

            if ($competencia === null || $competencia->registroPreguntas()->exists()) {
                continue;
            }

            // El repositorio solo crea registros para competencias activas; la inactiva ya viene así de
            // CompetenciasSeeder, por lo que se reactiva un instante (sin tocar `updated_at`) y se restaura.
            $estadoOriginal = $competencia->estado;
            if ($estadoOriginal !== 'Activo') {
                DB::table('competencias')->where('id', $competencia->id)->update(['estado' => 'Activo']);
            }

            try {
                $repositorio->crear($competencia->id, $preguntas, $actorId);
            } finally {
                if ($estadoOriginal !== 'Activo') {
                    DB::table('competencias')->where('id', $competencia->id)->update(['estado' => $estadoOriginal]);
                }
            }
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function registros(): array
    {
        return [
            'Orientación a resultados' => [
                'Cuéntame de una meta exigente que te hayas propuesto y cómo la alcanzaste.',
                '¿Cómo das seguimiento a tus indicadores para saber si vas por buen camino?',
                'Describe una situación en la que un plazo cambió y qué hiciste para cumplirlo.',
                '¿Qué haces cuando un resultado se aleja de lo esperado?',
            ],
            'Trabajo en equipo' => [
                'Cuéntame de un logro que dependió de la colaboración de todo el equipo.',
                '¿Cómo reaccionas cuando una persona del equipo no está de acuerdo contigo?',
                'Describe una ocasión en la que ayudaste a un compañero a cumplir su tarea.',
                '¿Cómo compartes información con quienes trabajan contigo?',
            ],
            'Liderazgo' => [
                'Describe una situación en la que guiaste a un grupo hacia un objetivo difícil.',
                '¿Cómo decides qué tareas delegar y a quién?',
                'Cuéntame cómo diste retroalimentación a alguien que no estaba rindiendo como se esperaba.',
                '¿Qué haces para desarrollar a las personas de tu equipo?',
            ],
            'Gestión de proyectos' => [
                'Explica cómo planificas un proyecto desde su inicio hasta su cierre.',
                '¿Cómo identificas y gestionas los riesgos de un proyecto?',
                'Describe un proyecto que se retrasó y las medidas que tomaste.',
                '¿Cómo comunicas el avance a las personas interesadas?',
            ],
            'Adaptabilidad' => [
                'Cuéntame de un cambio importante en tu trabajo y cómo lo afrontaste.',
                '¿Cómo reaccionas cuando cambian las prioridades de un día para otro?',
                'Describe una situación en la que tuviste que aprender algo nuevo con rapidez.',
                '¿Cómo ayudas a otras personas a adaptarse a un cambio?',
            ],
        ];
    }
}
