<?php

namespace Database\Seeders;

use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoGradoModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;

class CompetenciasSeeder extends Seeder
{
    /**
     * Siembra siete competencias completas (4 grados, 16 comportamientos y 4 preguntas), una de ellas inactiva y
     * con las tres escalas de grados (idempotente por nombre). Pasa por el repositorio para que el código
     * COMP-NNNN y la auditoría se asignen igual que en la aplicación.
     */
    public function run(): void
    {
        $repositorio = app(CompetenciaRepositoryInterface::class);
        // Autor de los datos sembrados: el administrador de prueba, si existe.
        $actorId = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $actorId = $actorId === null ? null : (int) $actorId;

        foreach ($this->competencias() as $datos) {
            if (CompetenciaModel::query()->where('nombre_clave', CompetenciaModel::claveDeNombre($datos['nombre']))->exists()) {
                continue;
            }

            $escalaId = (int) TipoGradoModel::query()->where('nombre', $datos['escala'])->value('id');

            $competencia = $repositorio->crear(
                $datos['nombre'],
                $datos['descripcion'],
                $datos['tipo'],
                $escalaId,
                $datos['categorias'] ?? null,
                array_values($datos['grados']),
                array_values($datos['comportamientos']),
                $datos['preguntas'],
                $actorId,
            );

            if ($datos['estado'] === EstadoCompetencia::Inactivo) {
                $repositorio->cambiarEstado($competencia->id, EstadoCompetencia::Inactivo, $actorId);
            }
        }
    }

    /**
     * @return array<int, array{nombre: string, descripcion: string, tipo: TipoCompetencia, escala: string, categorias?: array<int, string>, grados: array<int, string>, comportamientos: array<int, array<int, string>>, preguntas: array<int, string>, estado: EstadoCompetencia}>
     */
    private function competencias(): array
    {
        $cardinal = TipoCompetencia::Cardinal;
        $especifica = TipoCompetencia::Especifica;
        $activo = EstadoCompetencia::Activo;

        return [
            [
                'nombre' => 'Orientación a resultados',
                'descripcion' => 'Capacidad de enfocar el trabajo en alcanzar metas claras con calidad y en los plazos acordados.',
                'tipo' => $cardinal,
                'escala' => 'Letras',
                'grados' => [
                    'Define metas desafiantes para su área, anticipa obstáculos y asegura resultados sostenidos superiores a lo esperado.',
                    'Cumple y supera sus metas con autonomía, y ajusta su plan cuando cambian las condiciones.',
                    'Cumple sus metas en tiempo y forma con supervisión ocasional.',
                    'Realiza las tareas asignadas y necesita seguimiento para cumplir los plazos.',
                ],
                'comportamientos' => [
                    [
                        'Define metas desafiantes y medibles para su área.',
                        'Anticipa obstáculos y propone alternativas antes de que afecten los plazos.',
                        'Asegura resultados sostenidos por encima de lo esperado.',
                        'Reconoce y comparte los logros del equipo.',
                    ],
                    [
                        'Cumple y supera sus metas con autonomía.',
                        'Ajusta su plan cuando cambian las condiciones.',
                        'Da seguimiento periódico a sus indicadores.',
                        'Prioriza lo que genera más valor.',
                    ],
                    [
                        'Cumple sus metas en tiempo y forma.',
                        'Pide apoyo oportuno cuando prevé un retraso.',
                        'Informa el avance a su jefatura.',
                        'Corrige sus errores a tiempo.',
                    ],
                    [
                        'Realiza las tareas asignadas.',
                        'Requiere seguimiento para cumplir los plazos.',
                        'Entrega lo solicitado en el plazo acordado.',
                        'Acepta la retroalimentación.',
                    ],
                ],
                'preguntas' => [
                    'Cuéntame de una meta exigente que te hayas propuesto y cómo la alcanzaste.',
                    '¿Cómo das seguimiento a tus indicadores para saber si vas por buen camino?',
                    'Describe una situación en la que un plazo cambió y qué hiciste para cumplirlo.',
                    '¿Qué haces cuando un resultado se aleja de lo esperado?',
                ],
                'estado' => $activo,
            ],
            [
                'nombre' => 'Trabajo en equipo',
                'descripcion' => 'Colaborar con otras personas para lograr objetivos comunes, compartiendo información y apoyo.',
                'tipo' => $cardinal,
                'escala' => 'Números',
                'grados' => [
                    'Construye equipos cohesionados, media en los conflictos y promueve la colaboración entre áreas.',
                    'Coordina esfuerzos con otros, comparte conocimiento y resuelve diferencias de forma constructiva.',
                    'Colabora con su equipo y cumple su parte en las tareas conjuntas.',
                    'Participa en el equipo cuando se le solicita y trabaja principalmente de forma individual.',
                ],
                'comportamientos' => [
                    [
                        'Construye equipos cohesionados entre áreas.',
                        'Media en los conflictos y logra acuerdos duraderos.',
                        'Reconoce los aportes de los demás.',
                        'Promueve un clima de confianza.',
                    ],
                    [
                        'Coordina esfuerzos y comparte conocimiento.',
                        'Resuelve las diferencias de forma constructiva.',
                        'Distribuye tareas según las fortalezas del equipo.',
                        'Da seguimiento a los compromisos asumidos.',
                    ],
                    [
                        'Colabora y cumple su parte en las tareas conjuntas.',
                        'Escucha las ideas de sus compañeros.',
                        'Comparte información de forma oportuna.',
                        'Ofrece ayuda cuando lo considera necesario.',
                    ],
                    [
                        'Participa cuando se le solicita.',
                        'Trabaja principalmente de forma individual.',
                        'Acepta las indicaciones del grupo.',
                        'Pide ayuda cuando la necesita.',
                    ],
                ],
                'preguntas' => [
                    'Cuéntame de un logro que dependió de la colaboración de todo el equipo.',
                    '¿Cómo reaccionas cuando una persona del equipo no está de acuerdo contigo?',
                    'Describe una ocasión en la que ayudaste a un compañero a cumplir su tarea.',
                    '¿Cómo compartes información con quienes trabajan contigo?',
                ],
                'estado' => $activo,
            ],
            [
                'nombre' => 'Comunicación efectiva',
                'descripcion' => 'Transmitir ideas e información de forma clara y respetuosa, y escuchar activamente a los demás.',
                'tipo' => $cardinal,
                'escala' => 'Categorías',
                'categorias' => ['Excelente', 'Muy buena', 'Buena', 'Normal'],
                'grados' => [
                    'Adapta su mensaje a cualquier audiencia, influye con argumentos sólidos y facilita acuerdos complejos.',
                    'Expresa ideas con claridad, escucha activamente y da retroalimentación útil.',
                    'Comunica lo necesario con claridad en situaciones habituales.',
                    'Transmite información básica y requiere orientación para estructurar sus mensajes.',
                ],
                'comportamientos' => [
                    [
                        'Comunica la visión institucional.',
                        'Dialoga con audiencias internas y externas.',
                        'Maneja conversaciones difíciles.',
                        'Genera confianza al comunicar.',
                    ],
                    [
                        'Adapta su mensaje a cada audiencia.',
                        'Presenta ideas con impacto.',
                        'Escucha activamente.',
                        'Da y recibe retroalimentación.',
                    ],
                    [
                        'Expresa ideas con claridad, de forma oral y escrita.',
                        'Escucha a sus interlocutores.',
                        'Resume la información clave.',
                        'Mantiene un trato cordial.',
                    ],
                    [
                        'Transmite información básica de forma comprensible.',
                        'Usa los canales establecidos.',
                        'Pregunta cuando no entiende.',
                        'Responde a tiempo.',
                    ],
                ],
                'preguntas' => [
                    '¿Cómo adaptas tu mensaje a distintas audiencias?',
                    'Describe una conversación difícil que manejaste bien.',
                    '¿Cómo te aseguras de que te entendieron?',
                    '¿Cómo escuchas a quien piensa distinto?',
                ],
                'estado' => $activo,
            ],
            [
                'nombre' => 'Liderazgo',
                'descripcion' => 'Guiar e inspirar a otras personas hacia una visión compartida, desarrollando su potencial.',
                'tipo' => $cardinal,
                'escala' => 'Letras',
                'grados' => [
                    'Inspira una visión, desarrolla a otros líderes y toma decisiones estratégicas en entornos inciertos.',
                    'Dirige equipos con claridad, delega con criterio y desarrolla a su gente.',
                    'Orienta a su grupo en tareas concretas y da el ejemplo.',
                    'Asume la guía de actividades puntuales con apoyo de su jefatura.',
                ],
                'comportamientos' => [
                    [
                        'Inspira una visión y desarrolla a otros líderes.',
                        'Lidera procesos de cambio.',
                        'Es referente de los valores de la organización.',
                        'Toma decisiones estratégicas en entornos inciertos.',
                    ],
                    [
                        'Dirige equipos con claridad.',
                        'Delega con criterio.',
                        'Desarrolla a su gente.',
                        'Da retroalimentación frecuente y específica.',
                    ],
                    [
                        'Orienta a su grupo en tareas concretas.',
                        'Da el ejemplo.',
                        'Reconoce el buen desempeño.',
                        'Asume la responsabilidad del resultado.',
                    ],
                    [
                        'Asume la guía de actividades puntuales.',
                        'Consulta a su jefatura ante dudas.',
                        'Sigue el ejemplo de sus líderes.',
                        'Muestra iniciativa.',
                    ],
                ],
                'preguntas' => [
                    'Describe una situación en la que guiaste a un grupo hacia un objetivo difícil.',
                    '¿Cómo decides qué tareas delegar y a quién?',
                    'Cuéntame cómo diste retroalimentación a alguien que no estaba rindiendo como se esperaba.',
                    '¿Qué haces para desarrollar a las personas de tu equipo?',
                ],
                'estado' => $activo,
            ],
            [
                'nombre' => 'Pensamiento analítico',
                'descripcion' => 'Descomponer situaciones complejas, identificar causas y tomar decisiones basadas en datos.',
                'tipo' => $especifica,
                'escala' => 'Letras',
                'grados' => [
                    'Modela problemas complejos, integra múltiples fuentes de datos y anticipa escenarios futuros.',
                    'Analiza información de varias fuentes, detecta causas y propone soluciones fundamentadas.',
                    'Resuelve problemas habituales organizando la información disponible.',
                    'Identifica problemas simples y necesita apoyo para analizarlos.',
                ],
                'comportamientos' => [
                    [
                        'Modela problemas complejos.',
                        'Integra múltiples fuentes de datos.',
                        'Anticipa escenarios futuros.',
                        'Asesora la toma de decisiones con evidencia.',
                    ],
                    [
                        'Analiza información de varias fuentes.',
                        'Detecta las causas de un problema.',
                        'Propone soluciones fundamentadas.',
                        'Comunica sus hallazgos con claridad.',
                    ],
                    [
                        'Resuelve problemas habituales.',
                        'Organiza la información disponible.',
                        'Identifica tendencias básicas.',
                        'Documenta sus procedimientos.',
                    ],
                    [
                        'Identifica problemas simples.',
                        'Recopila y ordena datos según instrucciones.',
                        'Registra la información con exactitud.',
                        'Consulta sus dudas a su jefatura.',
                    ],
                ],
                'preguntas' => [
                    'Explica un análisis que influyó en una decisión importante.',
                    '¿Cómo verificas la calidad de la información que usas?',
                    '¿Qué haces cuando los datos se contradicen?',
                    '¿Cómo comunicas tus hallazgos a quien no es técnico?',
                ],
                'estado' => $activo,
            ],
            [
                'nombre' => 'Gestión de proyectos',
                'descripcion' => 'Planificar, ejecutar y controlar proyectos en alcance, tiempo, costo y calidad.',
                'tipo' => $especifica,
                'escala' => 'Letras',
                'grados' => [
                    'Dirige portafolios de proyectos, define metodologías y gestiona riesgos a nivel organizacional.',
                    'Gestiona proyectos completos con planes, riesgos y seguimiento formales.',
                    'Planifica y da seguimiento a proyectos pequeños con apoyo metodológico.',
                    'Colabora en tareas de un proyecto bajo la guía de su responsable.',
                ],
                'comportamientos' => [
                    [
                        'Dirige portafolios de proyectos.',
                        'Define metodologías.',
                        'Gestiona riesgos a nivel organizacional.',
                        'Alinea los proyectos con la estrategia.',
                    ],
                    [
                        'Gestiona proyectos completos con planes y seguimiento formales.',
                        'Administra presupuesto y recursos.',
                        'Coordina a los interesados.',
                        'Controla alcance, tiempo y costo.',
                    ],
                    [
                        'Planifica proyectos pequeños.',
                        'Da seguimiento a tareas y plazos.',
                        'Registra riesgos y acuerdos.',
                        'Comunica el avance al responsable.',
                    ],
                    [
                        'Colabora en tareas de un proyecto.',
                        'Sigue las indicaciones de su responsable.',
                        'Registra su avance.',
                        'Reporta los problemas que detecta.',
                    ],
                ],
                'preguntas' => [
                    'Explica cómo planificas un proyecto desde su inicio hasta su cierre.',
                    '¿Cómo identificas y gestionas los riesgos de un proyecto?',
                    'Describe un proyecto que se retrasó y las medidas que tomaste.',
                    '¿Cómo comunicas el avance a las personas interesadas?',
                ],
                'estado' => $activo,
            ],
            [
                'nombre' => 'Adaptabilidad',
                'descripcion' => 'Ajustarse con rapidez y actitud positiva a cambios en el entorno, las prioridades o los métodos de trabajo.',
                'tipo' => $cardinal,
                'escala' => 'Letras',
                'grados' => [
                    'Lidera a otros a través del cambio y convierte la incertidumbre en oportunidades.',
                    'Se adapta con rapidez a nuevos contextos y ayuda a su equipo a hacerlo.',
                    'Acepta los cambios y ajusta su forma de trabajar con poca resistencia.',
                    'Se adapta a los cambios con dificultad y requiere acompañamiento.',
                ],
                'comportamientos' => [
                    [
                        'Lidera a otros a través del cambio.',
                        'Convierte la incertidumbre en oportunidades.',
                        'Anticipa los cambios del entorno.',
                        'Mantiene al equipo enfocado durante la transición.',
                    ],
                    [
                        'Se adapta con rapidez a nuevos contextos.',
                        'Ayuda a su equipo a hacerlo.',
                        'Propone mejoras tras el cambio.',
                        'Ajusta sus prioridades ante imprevistos.',
                    ],
                    [
                        'Acepta los cambios con poca resistencia.',
                        'Ajusta su forma de trabajar.',
                        'Pide información cuando algo cambia.',
                        'Mantiene una actitud positiva.',
                    ],
                    [
                        'Se adapta a los cambios con dificultad.',
                        'Requiere acompañamiento.',
                        'Necesita instrucciones detalladas.',
                        'Pregunta antes de modificar su forma de trabajar.',
                    ],
                ],
                'preguntas' => [
                    'Cuéntame de un cambio importante en tu trabajo y cómo lo afrontaste.',
                    '¿Cómo reaccionas cuando cambian las prioridades de un día para otro?',
                    'Describe una situación en la que tuviste que aprender algo nuevo con rapidez.',
                    '¿Qué haces para ayudar a otros a adaptarse a un cambio?',
                ],
                'estado' => EstadoCompetencia::Inactivo,
            ],
        ];
    }
}
