<?php

namespace Database\Seeders;

use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;

class CompetenciasSeeder extends Seeder
{
    /**
     * Siembra siete competencias de prueba, una de ellas inactiva (idempotente por nombre).
     * Pasa por el repositorio para que el código COMP-NNNN y la auditoría se asignen igual que en la aplicación.
     */
    public function run(): void
    {
        $repositorio = app(CompetenciaRepositoryInterface::class);
        // Autor de los datos sembrados: el administrador de prueba, si existe.
        $actorId = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $actorId = $actorId === null ? null : (int) $actorId;

        foreach ($this->competencias() as [$nombre, $descripcion, $tipo, $grados, $estado]) {
            if (CompetenciaModel::query()->where('nombre_clave', CompetenciaModel::claveDeNombre($nombre))->exists()) {
                continue;
            }

            $competencia = $repositorio->crear($nombre, $descripcion, $tipo, $grados, $actorId);

            if ($estado === EstadoCompetencia::Inactivo) {
                $repositorio->cambiarEstado($competencia->id, EstadoCompetencia::Inactivo, $actorId);
            }
        }
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: TipoCompetencia, 3: array<string, string>, 4: EstadoCompetencia}>
     */
    private function competencias(): array
    {
        $cardinal = TipoCompetencia::Cardinal;
        $especifica = TipoCompetencia::Especifica;
        $activo = EstadoCompetencia::Activo;

        return [
            ['Orientación a resultados', 'Capacidad de enfocar el trabajo en alcanzar metas claras con calidad y en los plazos acordados.', $cardinal, [
                'A' => 'Define metas desafiantes para su área, anticipa obstáculos y asegura resultados sostenidos superiores a lo esperado.',
                'B' => 'Cumple y supera sus metas con autonomía, y ajusta su plan cuando cambian las condiciones.',
                'C' => 'Cumple sus metas en tiempo y forma con supervisión ocasional.',
                'D' => 'Realiza las tareas asignadas y necesita seguimiento para cumplir los plazos.',
            ], $activo],
            ['Trabajo en equipo', 'Colaborar con otras personas para lograr objetivos comunes, compartiendo información y apoyo.', $cardinal, [
                'A' => 'Construye equipos cohesionados, media en los conflictos y promueve la colaboración entre áreas.',
                'B' => 'Coordina esfuerzos con otros, comparte conocimiento y resuelve diferencias de forma constructiva.',
                'C' => 'Colabora con su equipo y cumple su parte en las tareas conjuntas.',
                'D' => 'Participa en el equipo cuando se le solicita y trabaja principalmente de forma individual.',
            ], $activo],
            ['Comunicación efectiva', 'Transmitir ideas e información de forma clara y respetuosa, y escuchar activamente a los demás.', $cardinal, [
                'A' => 'Adapta su mensaje a cualquier audiencia, influye con argumentos sólidos y facilita acuerdos complejos.',
                'B' => 'Expresa ideas con claridad, escucha activamente y da retroalimentación útil.',
                'C' => 'Comunica lo necesario con claridad en situaciones habituales.',
                'D' => 'Transmite información básica y requiere orientación para estructurar sus mensajes.',
            ], $activo],
            ['Liderazgo', 'Guiar e inspirar a otras personas hacia una visión compartida, desarrollando su potencial.', $cardinal, [
                'A' => 'Inspira una visión, desarrolla a otros líderes y toma decisiones estratégicas en entornos inciertos.',
                'B' => 'Dirige equipos con claridad, delega con criterio y desarrolla a su gente.',
                'C' => 'Orienta a su grupo en tareas concretas y da el ejemplo.',
                'D' => 'Asume la guía de actividades puntuales con apoyo de su jefatura.',
            ], $activo],
            ['Pensamiento analítico', 'Descomponer situaciones complejas, identificar causas y tomar decisiones basadas en datos.', $especifica, [
                'A' => 'Modela problemas complejos, integra múltiples fuentes de datos y anticipa escenarios futuros.',
                'B' => 'Analiza información de varias fuentes, detecta causas y propone soluciones fundamentadas.',
                'C' => 'Resuelve problemas habituales organizando la información disponible.',
                'D' => 'Identifica problemas simples y necesita apoyo para analizarlos.',
            ], $activo],
            ['Gestión de proyectos', 'Planificar, ejecutar y controlar proyectos en alcance, tiempo, costo y calidad.', $especifica, [
                'A' => 'Dirige portafolios de proyectos, define metodologías y gestiona riesgos a nivel organizacional.',
                'B' => 'Gestiona proyectos completos con planes, riesgos y seguimiento formales.',
                'C' => 'Planifica y da seguimiento a proyectos pequeños con apoyo metodológico.',
                'D' => 'Colabora en tareas de un proyecto bajo la guía de su responsable.',
            ], $activo],
            ['Adaptabilidad', 'Ajustarse con rapidez y actitud positiva a cambios en el entorno, las prioridades o los métodos de trabajo.', $cardinal, [
                'A' => 'Lidera a otros a través del cambio y convierte la incertidumbre en oportunidades.',
                'B' => 'Se adapta con rapidez a nuevos contextos y ayuda a su equipo a hacerlo.',
                'C' => 'Acepta los cambios y ajusta su forma de trabajar con poca resistencia.',
                'D' => 'Se adapta a los cambios con dificultad y requiere acompañamiento.',
            ], EstadoCompetencia::Inactivo],
        ];
    }
}
