<?php

namespace Database\Seeders;

use App\Modules\Desarrollo\Domain\Entities\Dnf;
use App\Modules\Desarrollo\Domain\Entities\ResultadoColaborador;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeBrecha;
use App\Modules\Desarrollo\Domain\Services\NormalizadorDeNombre;
use App\Modules\Desarrollo\Domain\ValueObjects\AlcanceDnf;
use App\Modules\Desarrollo\Domain\ValueObjects\CodigoActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\CodigoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoCompetenciaDnf;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadAsistenciaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AreaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaCursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaMicrocontenidoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\EncuestaSatisfaccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ImpactoAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroIntentoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroPreguntaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroProgresoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroRespuestaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionCompetenciaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionParticipanteModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\SeguimientoImpactoModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;
use App\Modules\GestionPersonal\Domain\ValueObjects\CodigoColaborador;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos de demostración ampliados para todo el módulo Desarrollo (opt-in, NO forma parte de DatabaseSeeder):
 *
 *   php artisan db:seed --class=DatabaseSeeder        (base)
 *   php artisan db:seed --class=DesarrolloDemoSeeder  (esta ampliación)
 *
 * Idempotente: no hace nada si ya existe el colaborador de prueba 1700000001. Añade ocho colaboradores de prueba, más cursos y
 * micro-contenidos conectados a TODAS las competencias, progresos de micro-learning, tres DNF, un plan «Ejecutando» con cuatro
 * acciones (cursos del catálogo), sus actividades con asistencias, encuestas, seguimientos 30/60/90 e impactos.
 */
class DesarrolloDemoSeeder extends Seeder
{
    private const TENANT = 1;

    private int $admin = 0;

    public function run(): void
    {
        if (ColaboradorModel::query()->where('tenant_id', self::TENANT)->where('numero_identificacion', '1700000001')->exists()) {
            return;
        }
        $admin = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $competencias = [];
        foreach (EstructuraFacade::getTodasLasCompetencias() as $c) {
            $competencias[$c['nombre']] = $c;
        }
        if ($admin === null || count($competencias) < 7) {
            $this->command?->warn('Faltan usuarios o competencias: ejecute antes DatabaseSeeder.');

            return;
        }
        $this->admin = (int) $admin;

        DB::transaction(function () use ($competencias) {
            $personas = $this->colaboradores();
            $cursos = $this->catalogo($competencias);
            $this->microLearning($personas);
            $this->dnfs($personas, $competencias);
            $this->planYActividades($personas, $competencias, $cursos);
        });
    }

    /** @return list<int> ids de las ocho fichas de prueba */
    private function colaboradores(): array
    {
        $normalizador = new NormalizadorDeTexto;
        $datos = [
            ['Carlos', 'Andrade Ruiz'], ['Daniela', 'Cevallos Mena'], ['Esteban', 'Villacís Toapanta'], ['Fernanda', 'Guerrero Lara'],
            ['Gabriel', 'Mosquera Salas'], ['Helena', 'Proaño Jiménez'], ['Iván', 'Tapia Burbano'], ['Julia', 'Escobar Naranjo'],
        ];
        $ids = [];
        foreach ($datos as $i => [$nombres, $apellidos]) {
            $numero = (string) (1700000001 + $i);
            $clave = $normalizador->claveDeIdentificacion($numero);
            $modelo = ColaboradorModel::query()->create([
                'tenant_id' => self::TENANT, 'codigo' => 'TMP-1-'.$clave, 'tipo_identificacion' => 'Cedula',
                'numero_identificacion' => $numero, 'identificacion_clave' => $clave, 'nombres' => $nombres, 'apellidos' => $apellidos,
                'nombres_busqueda' => $normalizador->busquedaDeTexto($nombres), 'apellidos_busqueda' => $normalizador->busquedaDeTexto($apellidos),
                'correo_contacto' => Str::slug($nombres.'.'.explode(' ', $apellidos)[0], '.').'@example.test',
                'version' => 1, 'operacion_id' => (string) Str::uuid(), 'operacion_hash' => hash('sha256', 'demo-desarrollo-'.$numero),
                'created_by' => $this->admin, 'updated_by' => $this->admin,
            ]);
            $modelo->codigo = CodigoColaborador::desdeId((int) $modelo->id);
            $modelo->save();
            $ids[] = (int) $modelo->id;
        }

        return $ids;
    }

    /**
     * Más cursos y micro-contenidos, y vínculos para que cada competencia tenga oferta (excepto Orientación a resultados,
     * que conserva solo un curso archivado: caso de prueba del catálogo).
     *
     * @param  array<string, array<string, mixed>>  $competencias
     * @return array<string, int> id de cursos por clave
     */
    private function catalogo(array $competencias): array
    {
        $cursos = [];
        foreach (CursoModel::query()->get() as $c) {
            $cursos[$c->nombre] = (int) $c->id;
        }
        foreach ([
            ['Negociación y manejo de conflictos', 'Mixto', 180, 'Marta Ríos'],
            ['Excel avanzado para análisis', 'Online', 240, 'Diego Paredes'],
            ['Metodologías ágiles: Scrum', 'Online', 360, 'Carla Vega'],
            ['Resiliencia y adaptación al cambio', 'Presencial', 120, 'Ana Salas'],
        ] as [$nombre, $tipo, $min, $formador]) {
            $cursos[$nombre] = (int) CursoModel::query()->create(['nombre' => $nombre, 'tipo' => $tipo, 'duracion_minutos' => $min, 'formador' => $formador, 'estado' => 'Disponible'])->id;
        }

        $micros = [];
        foreach ([
            ['Primeros pasos con tableros de datos', 'Video', 9, 'Pensamiento analítico'],
            ['Cómo interpretar un gráfico', 'Articulo', 7, 'Pensamiento analítico'],
            ['Checklist para planificar un proyecto', 'PDF', 4, 'Gestión de proyectos'],
            ['Manejo del estrés ante el cambio', 'Video', 8, 'Adaptabilidad'],
            ['Delegar sin perder el control', 'Articulo', 11, 'Liderazgo'],
            ['Escucha activa en 5 pasos', 'Video', 6, 'Comunicación efectiva'],
            ['Roles y acuerdos de un equipo', 'PDF', 5, 'Trabajo en equipo'],
        ] as [$titulo, $tipo, $min, $competencia]) {
            $micro = MicrocontenidoModel::query()->create([
                'titulo' => $titulo, 'tipo' => $tipo, 'duracion_minutos' => $min, 'estado' => 'Publicado',
                'url' => 'https://contenido.hrms.test/demo/'.Str::slug($titulo), 'created_by' => $this->admin, 'updated_by' => $this->admin,
            ]);
            $micro->forceFill(['codigo' => 'MICRO-'.str_pad((string) $micro->id, 4, '0', STR_PAD_LEFT)])->save();
            MicroHistorialModel::query()->create(['microcontenido_id' => $micro->id, 'estado_anterior' => null, 'estado_nuevo' => 'Borrador', 'user_id' => $this->admin]);
            MicroHistorialModel::query()->create(['microcontenido_id' => $micro->id, 'estado_anterior' => 'Borrador', 'estado_nuevo' => 'Publicado', 'user_id' => $this->admin]);
            CompetenciaMicrocontenidoModel::query()->firstOrCreate(['competencia_id' => (int) $competencias[$competencia]['id'], 'microcontenido_id' => $micro->id], ['created_by' => $this->admin]);
            $micros[$titulo] = (int) $micro->id;
        }

        $cursosPorCompetencia = [
            'Comunicación efectiva' => ['Negociación y manejo de conflictos'],
            'Pensamiento analítico' => ['Análisis de datos con hojas de cálculo', 'Excel avanzado para análisis'],
            'Gestión de proyectos' => ['Metodologías ágiles: Scrum'],
            'Adaptabilidad' => ['Resiliencia y adaptación al cambio'],
            'Liderazgo' => ['Negociación y manejo de conflictos'],
        ];
        foreach ($cursosPorCompetencia as $competencia => $lista) {
            foreach ($lista as $nombre) {
                CompetenciaCursoModel::query()->firstOrCreate(['competencia_id' => (int) $competencias[$competencia]['id'], 'curso_id' => $cursos[$nombre]], ['created_by' => $this->admin]);
            }
        }

        // Pensamiento analítico no tenía área formativa: se le asigna «Técnica y datos».
        $area = AreaFormativaModel::query()->where('nombre', 'Técnica y datos')->value('id');
        CompetenciaFormativaModel::query()->updateOrCreate(
            ['competencia_id' => (int) $competencias['Pensamiento analítico']['id']],
            ['area_formativa_id' => $area, 'version' => 2, 'updated_by' => $this->admin],
        );

        return $cursos;
    }

    /**
     * @param  list<int>  $personas
     */
    private function microLearning(array $personas): void
    {
        $micros = MicrocontenidoModel::query()->where('estado', 'Publicado')->orderBy('id')->get();
        $quiz = $micros->firstWhere('tipo', 'Quiz');
        $otros = $micros->where('tipo', '!=', 'Quiz')->values();
        $ahora = now();

        foreach ($personas as $i => $colaboradorId) {
            // Cada persona avanza en 2-4 contenidos, algunos completados y otros en progreso.
            foreach ($otros->slice($i % 3, 2 + ($i % 3)) as $j => $micro) {
                $completado = ($i + $j) % 2 === 0;
                if (MicroProgresoModel::query()->where('microcontenido_id', $micro->id)->where('colaborador_id', $colaboradorId)->exists()) {
                    continue;
                }
                MicroProgresoModel::query()->create([
                    'microcontenido_id' => $micro->id, 'colaborador_id' => $colaboradorId, 'tenant_id' => self::TENANT,
                    'estado' => $completado ? 'Completado' : 'EnProgreso',
                    'posicion_segundos' => ! $completado && $micro->tipo === 'Video' ? 40 + $i * 20 : null,
                    'fecha_inicio' => $ahora->copy()->subDays(10 - $j), 'fecha_completado' => $completado ? $ahora->copy()->subDays(9 - $j) : null,
                ]);
            }
            if ($quiz !== null && $i % 2 === 0 && ! MicroProgresoModel::query()->where('microcontenido_id', $quiz->id)->where('colaborador_id', $colaboradorId)->exists()) {
                $this->intentoDeQuiz($quiz, $colaboradorId, $i);
            }
        }
    }

    private function intentoDeQuiz(MicrocontenidoModel $quiz, int $colaboradorId, int $semilla): void
    {
        $progreso = MicroProgresoModel::query()->create([
            'microcontenido_id' => $quiz->id, 'colaborador_id' => $colaboradorId, 'tenant_id' => self::TENANT, 'estado' => 'Completado',
            'fecha_inicio' => now()->subDays(6), 'fecha_completado' => now()->subDays(6),
        ]);
        $preguntas = MicroPreguntaModel::query()->where('microcontenido_id', $quiz->id)->with('opciones')->orderBy('orden')->get();
        $intento = MicroIntentoModel::query()->create([
            'progreso_id' => $progreso->id, 'estado' => 'Finalizado', 'puntaje' => 0, 'total_preguntas' => $preguntas->count(), 'finalizado_en' => now()->subDays(6),
        ]);
        $aciertos = 0;
        foreach ($preguntas as $k => $pregunta) {
            $acierta = ($k + $semilla) % 4 !== 0;
            $opcion = $pregunta->opciones->firstWhere('es_correcta', $acierta) ?? $pregunta->opciones->first();
            $aciertos += $opcion->es_correcta ? 1 : 0;
            MicroRespuestaModel::query()->create(['intento_id' => $intento->id, 'pregunta_id' => $pregunta->id, 'opcion_id' => $opcion->id, 'es_correcta' => (bool) $opcion->es_correcta]);
        }
        $intento->update(['puntaje' => $aciertos]);
    }

    /**
     * Tres DNF: una individual de Carlos, una individual de Fernanda y una por departamento con los ocho colaboradores.
     *
     * @param  list<int>  $personas
     * @param  array<string, array<string, mixed>>  $competencias
     */
    private function dnfs(array $personas, array $competencias): void
    {
        $puestos = array_values(EstructuraFacade::getPuestosActivos());
        $unidades = array_values(EstructuraFacade::getUnidadesActivas());
        if ($puestos === [] || $unidades === []) {
            return;
        }
        $calculador = new CalculadorDeBrecha;
        $lista = array_values($competencias);
        $niveles = ['A', 'B', 'C', 'D'];

        // Resultado determinista: el nivel requerido y el actual rotan por persona y competencia (algunos Sin evaluación).
        $resultado = function (int $indice, int $personaId) use ($lista, $puestos, $niveles, $calculador): ResultadoColaborador {
            $filas = [];
            foreach ($lista as $k => $c) {
                $requerido = $niveles[($indice + $k) % 3];
                $actual = ($indice + $k) % 7 === 0 ? null : $niveles[($indice * 2 + $k * 3) % 4];
                $filas[] = $calculador->calcular((int) $c['id'], TipoCompetenciaDnf::from($c['tipo'] === 'Cardinal' ? 'Cardinal' : 'Especifica'), NivelGrado::from($requerido), $actual === null ? null : NivelGrado::from($actual));
            }

            return new ResultadoColaborador($personaId, (int) $puestos[$indice % count($puestos)]['id'], $filas);
        };

        $repositorio = app(DnfRepositoryInterface::class);
        foreach ([0, 3] as $indice) {
            $repositorio->guardar(new Dnf(null, self::TENANT, null, AlcanceDnf::Individual, null, 0, null, null, null, $this->admin, null, [$resultado($indice, $personas[$indice])]));
        }
        $repositorio->guardar(new Dnf(
            null, self::TENANT, null, AlcanceDnf::Departamento, (int) $unidades[count($unidades) > 1 ? 1 : 0]['id'], 0, null, null, null, $this->admin, null,
            array_map(fn (int $id, int $i) => $resultado($i, $id), $personas, array_keys($personas)),
        ));
    }

    /**
     * @param  list<int>  $personas
     * @param  array<string, array<string, mixed>>  $competencias
     * @param  array<string, int>  $cursos
     */
    private function planYActividades(array $personas, array $competencias, array $cursos): void
    {
        $hoy = CarbonImmutable::today();
        $anio = (int) $hoy->year;
        $fecha = function (int $dias) use ($hoy, $anio): string {
            $f = $hoy->addDays($dias);

            return ($f->year < $anio ? CarbonImmutable::create($anio, 1, 1) : ($f->year > $anio ? CarbonImmutable::create($anio, 12, 31) : $f))->toDateString();
        };
        $numero = (int) PlanModel::query()->where('tenant_id', self::TENANT)->max('numero') + 1;
        $nombre = "Plan de capacitación Operaciones {$anio}";
        $plan = PlanModel::query()->create([
            'tenant_id' => self::TENANT, 'numero' => $numero, 'codigo' => CodigoPlan::desde($numero), 'nombre' => $nombre,
            'nombre_normalizado' => (new NormalizadorDeNombre)->normalizar($nombre), 'anio' => $anio, 'descripcion' => 'Plan integrado de demostración: cierra brechas de la DNF departamental.',
            'estado' => 'Ejecutando', 'aprobador_id' => $this->admin, 'origen_dnf_id' => DB::table('dnf')->where('tenant_id', self::TENANT)->max('id'),
            'fecha_aprobacion' => $hoy->subDays(75)->toDateTimeString(), 'created_by' => $this->admin, 'updated_by' => $this->admin,
        ]);
        foreach ([['Borrador', 'Revision', 80], ['Revision', 'Aprobado', 75], ['Aprobado', 'Ejecutando', 74]] as [$de, $a, $dias]) {
            $h = PlanHistorialModel::query()->create(['plan_id' => $plan->id, 'tipo' => 'Estado', 'valor_anterior' => $de, 'valor_nuevo' => $a, 'user_id' => $this->admin]);
            PlanHistorialModel::query()->whereKey($h->id)->toBase()->update(['created_at' => now()->subDays($dias)]);
        }

        // [nombre, curso, tipo, desde, hasta, horas, competencia, participantes, lugar]
        $acciones = [
            ['Comunicación asertiva (cohorte Operaciones)', 'Comunicación asertiva', 'Presencial', -60, -50, 12, 'Comunicación efectiva', $personas, 'Sala de capacitación, piso 3'],
            ['Gestión de proyectos con Scrum', 'Metodologías ágiles: Scrum', 'Virtual', -6, 12, 20, 'Gestión de proyectos', array_slice($personas, 0, 5), 'https://meet.ejemplo.edu.ec/scrum'],
            ['Excel avanzado para análisis', 'Excel avanzado para análisis', 'ELearning', 20, 45, 16, 'Pensamiento analítico', array_slice($personas, 2, 6), 'https://aula.ejemplo.edu.ec/excel'],
            ['Mentoring para jefaturas', null, 'Mentoring', -45, -30, 10, 'Liderazgo', array_slice($personas, 0, 3), 'Oficina del mentor, piso 5'],
        ];
        $siguiente = (int) ActividadModel::query()->where('tenant_id', self::TENANT)->max('numero');
        $inicio = $siguiente;

        foreach ($acciones as [$titulo, $curso, $tipo, $desde, $hasta, $horas, $competencia, $inscritos, $lugar]) {
            $accion = PlanAccionModel::query()->create([
                'plan_id' => $plan->id, 'curso_id' => $curso === null ? null : $cursos[$curso], 'nombre' => $titulo, 'tipo' => $tipo,
                'fecha_inicio' => $fecha($desde), 'fecha_fin' => $fecha($hasta), 'duracion_horas' => $horas, 'formador' => 'Equipo de Desarrollo',
                'capacidad_maxima' => 20, 'costo' => $horas * 35, 'cancelada' => false, 'created_by' => $this->admin, 'updated_by' => $this->admin,
            ]);
            PlanAccionCompetenciaModel::query()->create(['plan_accion_id' => $accion->id, 'competencia_id' => (int) $competencias[$competencia]['id']]);
            foreach ($inscritos as $colaboradorId) {
                PlanAccionParticipanteModel::query()->create(['plan_accion_id' => $accion->id, 'colaborador_id' => $colaboradorId, 'created_by' => $this->admin]);
            }

            $siguiente++;
            $actividad = ActividadModel::query()->create([
                'tenant_id' => self::TENANT, 'numero' => $siguiente, 'codigo' => CodigoActividad::desde($siguiente), 'plan_accion_id' => $accion->id,
                'lugar_enlace' => $lugar, 'departamento_id' => null, 'version' => 1, 'created_by' => $this->admin, 'updated_by' => $this->admin,
            ]);
            if ($desde > 0) {
                continue; // Programada: sin asistencias todavía.
            }

            $finalizada = $hasta < 0;
            foreach ($inscritos as $k => $colaboradorId) {
                // En curso: solo se registra a los primeros; en Finalizada, a todos con ausencias y justificaciones.
                if (! $finalizada && $k > 2) {
                    continue;
                }
                $asistencia = $k % 5 === 3 ? 'Ausente' : ($k % 5 === 4 ? 'Justificado' : 'Presente');
                $calificacion = $asistencia === 'Presente' ? 3 + ($k % 3) : null;
                ActividadAsistenciaModel::query()->create([
                    'actividad_id' => $actividad->id, 'colaborador_id' => $colaboradorId, 'asistencia' => $asistencia, 'calificacion' => $calificacion,
                    'observaciones' => $asistencia === 'Justificado' ? 'Permiso aprobado por la jefatura' : null, 'registrado_by' => $this->admin,
                ]);
                ActividadHistorialModel::query()->create([
                    'actividad_id' => $actividad->id, 'colaborador_id' => $colaboradorId, 'asistencia_anterior' => null, 'asistencia_nueva' => $asistencia,
                    'calificacion_anterior' => null, 'calificacion_nueva' => $calificacion, 'user_id' => $this->admin,
                ]);
                if ($finalizada && $asistencia === 'Presente') {
                    EncuestaSatisfaccionModel::query()->create([
                        'tenant_id' => self::TENANT, 'actividad_id' => $actividad->id, 'colaborador_id' => $colaboradorId,
                        'calificacion' => 3 + (($k + $siguiente) % 3), 'comentario' => $k % 2 === 0 ? 'Contenido claro y aplicable a mi trabajo.' : null,
                    ]);
                    foreach ($hasta < -40 ? [30, 60] : [30] as $hito) {
                        SeguimientoImpactoModel::query()->create([
                            'tenant_id' => self::TENANT, 'actividad_id' => $actividad->id, 'colaborador_id' => $colaboradorId,
                            'hito' => $hito, 'aplicacion' => 3 + (($k + $hito) % 3), 'apoyo' => 3 + ($k % 2), 'comentario' => null,
                        ]);
                    }
                }
            }
            if ($finalizada) {
                [$indicador, $unidad, $antes, $despues, $beneficio] = $tipo === 'Presencial'
                    ? ['Reclamos por mala comunicación al mes', 'reclamos', 18, 11, 2400]
                    : ['Tiempo de decisión en comités', 'horas', 12, 8, 1500];
                ImpactoAccionModel::query()->create([
                    'tenant_id' => self::TENANT, 'plan_accion_id' => $accion->id, 'indicador' => $indicador, 'unidad' => $unidad,
                    'valor_antes' => $antes, 'valor_despues' => $despues, 'beneficio_estimado' => $beneficio,
                    'justificacion' => 'Medición comparada de los tres meses previos y posteriores a la acción.', 'created_by' => $this->admin, 'updated_by' => $this->admin,
                ]);
            }
        }
    }
}
