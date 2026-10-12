<?php

namespace Database\Seeders;

use App\Modules\Estructura\Domain\Repositories\ComportamientoRepositoryInterface;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComportamientosSeeder extends Seeder
{
    /**
     * Siembra cinco registros de comportamientos (idempotente por competencia), uno de ellos de una
     * competencia inactiva. Depende de CompetenciasSeeder; las demás competencias activas quedan sin
     * registro para poder probar la creación. Pasa por el repositorio como la aplicación.
     */
    public function run(): void
    {
        $repositorio = app(ComportamientoRepositoryInterface::class);
        $actorId = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $actorId = $actorId === null ? null : (int) $actorId;

        foreach ($this->registros() as $nombre => $comportamientos) {
            $competencia = CompetenciaModel::query()->where('nombre_clave', CompetenciaModel::claveDeNombre($nombre))->first();

            if ($competencia === null || $competencia->registroComportamientos()->exists()) {
                continue;
            }

            // El repositorio solo crea registros para competencias activas; la inactiva ya viene así de
            // CompetenciasSeeder, por lo que se reactiva un instante (sin tocar `updated_at`) y se restaura.
            $estadoOriginal = $competencia->estado;
            if ($estadoOriginal !== 'Activo') {
                DB::table('competencias')->where('id', $competencia->id)->update(['estado' => 'Activo']);
            }

            try {
                $repositorio->crear($competencia->id, $comportamientos, $actorId);
            } finally {
                if ($estadoOriginal !== 'Activo') {
                    DB::table('competencias')->where('id', $competencia->id)->update(['estado' => $estadoOriginal]);
                }
            }
        }
    }

    /**
     * @return array<string, array<string, array<int, string>>>
     */
    private function registros(): array
    {
        return [
            // 4 / 3 / 2 / 1
            'Orientación a resultados' => [
                'A' => [
                    'Define metas desafiantes y medibles para su área.',
                    'Anticipa obstáculos y propone alternativas antes de que afecten los plazos.',
                    'Asegura resultados sostenidos por encima de lo esperado.',
                    'Reconoce y comparte los logros del equipo.',
                ],
                'B' => [
                    'Cumple y supera sus metas con autonomía.',
                    'Ajusta su plan cuando cambian las condiciones.',
                    'Da seguimiento periódico a sus indicadores.',
                ],
                'C' => [
                    'Cumple sus metas en tiempo y forma.',
                    'Pide apoyo oportuno cuando prevé un retraso.',
                ],
                'D' => ['Realiza las tareas asignadas y requiere seguimiento para cumplir los plazos.'],
            ],
            // 2 / 2 / 2 / 2
            'Trabajo en equipo' => [
                'A' => ['Construye equipos cohesionados entre áreas.', 'Media en los conflictos y logra acuerdos duraderos.'],
                'B' => ['Coordina esfuerzos y comparte conocimiento.', 'Resuelve las diferencias de forma constructiva.'],
                'C' => ['Colabora y cumple su parte en las tareas conjuntas.', 'Escucha las ideas de sus compañeros.'],
                'D' => ['Participa cuando se le solicita.', 'Trabaja principalmente de forma individual.'],
            ],
            // 1 / 4 / 3 / 2
            'Liderazgo' => [
                'A' => ['Inspira una visión y desarrolla a otros líderes.'],
                'B' => [
                    'Dirige equipos con claridad.',
                    'Delega con criterio.',
                    'Desarrolla a su gente.',
                    'Da retroalimentación frecuente y específica.',
                ],
                'C' => ['Orienta a su grupo en tareas concretas.', 'Da el ejemplo.', 'Reconoce el buen desempeño.'],
                'D' => ['Asume la guía de actividades puntuales.', 'Consulta a su jefatura ante dudas.'],
            ],
            // 3 / 1 / 4 / 2
            'Gestión de proyectos' => [
                'A' => ['Dirige portafolios de proyectos.', 'Define metodologías.', 'Gestiona riesgos a nivel organizacional.'],
                'B' => ['Gestiona proyectos completos con planes y seguimiento formales.'],
                'C' => [
                    'Planifica proyectos pequeños.',
                    'Da seguimiento a tareas y plazos.',
                    'Registra riesgos y acuerdos.',
                    'Comunica el avance al responsable.',
                ],
                'D' => ['Colabora en tareas de un proyecto.', 'Sigue las indicaciones de su responsable.'],
            ],
            // 2 / 3 / 1 / 4 (competencia inactiva)
            'Adaptabilidad' => [
                'A' => ['Lidera a otros a través del cambio.', 'Convierte la incertidumbre en oportunidades.'],
                'B' => ['Se adapta con rapidez a nuevos contextos.', 'Ayuda a su equipo a hacerlo.', 'Propone mejoras tras el cambio.'],
                'C' => ['Acepta los cambios con poca resistencia.'],
                'D' => [
                    'Se adapta a los cambios con dificultad.',
                    'Requiere acompañamiento.',
                    'Necesita instrucciones detalladas.',
                    'Pregunta antes de modificar su forma de trabajar.',
                ],
            ],
        ];
    }
}
