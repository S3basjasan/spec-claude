<?php

namespace Database\Seeders;

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AreaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaCursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaMicrocontenidoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogoFormativoSeeder extends Seeder
{
    /**
     * Siembra el catálogo formativo de demostración sobre las siete competencias de CompetenciasSeeder
     * (idempotente: no hace nada si ya existen áreas formativas). Depende de CompetenciasSeeder.
     *
     * Casos de prueba: dos competencias activas sin cobertura (una sin recursos y otra solo con un curso
     * archivado), una competencia inactiva (Adaptabilidad) que conserva su curso, un curso usado por varias
     * competencias, una competencia activa sin área formativa y un curso con destino.
     *
     * Los cursos y micro-contenidos son provisionales: las features dueñas de esos recursos reemplazarán esta siembra.
     */
    public function run(): void
    {
        if (AreaFormativaModel::query()->exists()) {
            return;
        }

        $actorId = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $actorId = $actorId === null ? null : (int) $actorId;

        $competencias = [];
        foreach (EstructuraFacade::getTodasLasCompetencias() as $c) {
            $competencias[$c['nombre']] = $c['id'];
        }
        if (! isset($competencias['Liderazgo'])) {
            return;
        }

        DB::transaction(function () use ($competencias, $actorId) {
            $areas = [];
            foreach (['Liderazgo y gestión', 'Habilidades interpersonales', 'Técnica y datos', 'Desempeño y resultados'] as $nombre) {
                $areas[$nombre] = AreaFormativaModel::query()->create([
                    'nombre' => $nombre,
                    'nombre_clave' => AreaFormativaModel::claveDeNombre($nombre),
                ])->id;
            }

            $cursos = [];
            foreach ($this->cursos() as $clave => $datos) {
                $cursos[$clave] = CursoModel::query()->create($datos)->id;
            }
            $micros = [];
            foreach ($this->microcontenidos() as $clave => $datos) {
                $micros[$clave] = MicrocontenidoModel::query()->create($datos)->id;
            }

            foreach ($this->configuracion() as $competencia => [$area, $listaCursos, $listaMicros]) {
                $competenciaId = $competencias[$competencia] ?? null;
                if ($competenciaId === null) {
                    continue;
                }

                CompetenciaFormativaModel::query()->create([
                    'competencia_id' => $competenciaId,
                    'area_formativa_id' => $area === null ? null : $areas[$area],
                    'version' => 1,
                    'updated_by' => $actorId,
                ]);
                foreach ($listaCursos as $clave) {
                    CompetenciaCursoModel::query()->create(['competencia_id' => $competenciaId, 'curso_id' => $cursos[$clave], 'created_by' => $actorId]);
                }
                foreach ($listaMicros as $clave) {
                    CompetenciaMicrocontenidoModel::query()->create(['competencia_id' => $competenciaId, 'microcontenido_id' => $micros[$clave], 'created_by' => $actorId]);
                }
            }
        });
    }

    /**
     * Competencia => [área, claves de cursos, claves de micro-contenidos].
     *
     * @return array<string, array{0: string|null, 1: array<int, string>, 2: array<int, string>}>
     */
    private function configuracion(): array
    {
        return [
            'Comunicación efectiva' => ['Habilidades interpersonales', ['asertiva', 'escucha'], ['feedback_video']],
            'Trabajo en equipo' => ['Habilidades interpersonales', ['asertiva', 'colaborativo'], []],
            'Liderazgo' => ['Liderazgo y gestión', ['liderazgo'], ['guia_feedback']],
            'Gestión de proyectos' => ['Desempeño y resultados', ['proyectos'], ['pdca']],
            'Orientación a resultados' => ['Desempeño y resultados', ['taller_metas'], []],
            'Adaptabilidad' => ['Liderazgo y gestión', ['cambio'], []],
            'Pensamiento analítico' => [null, [], []],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function cursos(): array
    {
        return [
            'asertiva' => ['nombre' => 'Comunicación asertiva', 'tipo' => 'Online', 'duracion_minutos' => 90, 'formador' => 'Ana Salas', 'estado' => 'Disponible'],
            'escucha' => ['nombre' => 'Escucha activa en el trabajo', 'tipo' => 'Presencial', 'duracion_minutos' => 120, 'formador' => 'Luis Mora', 'estado' => 'Disponible'],
            'liderazgo' => ['nombre' => 'Liderazgo para jefaturas', 'tipo' => 'Mixto', 'duracion_minutos' => 240, 'formador' => 'Marta Ríos', 'estado' => 'Disponible', 'url_destino' => '/formacion/cursos/liderazgo'],
            'analisis' => ['nombre' => 'Análisis de datos con hojas de cálculo', 'tipo' => 'Online', 'duracion_minutos' => 180, 'formador' => 'Diego Paredes', 'estado' => 'Disponible'],
            'proyectos' => ['nombre' => 'Fundamentos de gestión de proyectos', 'tipo' => 'Online', 'duracion_minutos' => 300, 'formador' => 'Carla Vega', 'estado' => 'Disponible'],
            'cambio' => ['nombre' => 'Gestión del cambio personal', 'tipo' => 'Online', 'duracion_minutos' => 60, 'formador' => 'Ana Salas', 'estado' => 'Disponible'],
            'colaborativo' => ['nombre' => 'Trabajo colaborativo a distancia', 'tipo' => 'Online', 'duracion_minutos' => 45, 'formador' => 'Luis Mora', 'estado' => 'Disponible'],
            'taller_metas' => ['nombre' => 'Taller de metas y resultados', 'tipo' => 'Presencial', 'duracion_minutos' => 150, 'formador' => 'Carla Vega', 'estado' => 'Archivado'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function microcontenidos(): array
    {
        return [
            'guia_feedback' => ['nombre' => 'Guía rápida de retroalimentación', 'tipo' => 'Lectura', 'duracion_minutos' => 10, 'formador' => 'Marta Ríos', 'estado' => 'Disponible'],
            'feedback_video' => ['nombre' => 'Cómo dar y recibir retroalimentación', 'tipo' => 'Video', 'duracion_minutos' => 8, 'formador' => 'Ana Salas', 'estado' => 'Disponible'],
            'pdca' => ['nombre' => 'Infografía del ciclo PDCA', 'tipo' => 'Infografia', 'duracion_minutos' => 5, 'formador' => null, 'estado' => 'Disponible'],
            'pausas' => ['nombre' => 'Pausas activas para concentrarte', 'tipo' => 'Audio', 'duracion_minutos' => 6, 'formador' => null, 'estado' => 'Disponible'],
        ];
    }
}
