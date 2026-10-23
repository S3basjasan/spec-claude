<?php

namespace Database\Seeders;

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiCompromisoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHitoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PdisSeeder extends Seeder
{
    /**
     * Siembra doce planes (los cinco primeros descritos aquí y siete más sobre otras personas) de desarrollo individual del tenant 1: Mejora en Borrador sin acuerdo (Ana), Carrera en
     * Borrador con el acuerdo del responsable (Luis), Talento Acordado con una acción vinculada al Plan de Formación y un
     * hito vencido (María), Mejora Cerrado «Brecha cerrada» (Luis) y Carrera Cerrado «Parcial» (Ana). Los compromisos son
     * de competencia, experiencia y meta (la dimensión Conocimiento está desactivada: Estructura no tiene grados de
     * conocimiento) y las acciones cubren los siete tipos. Escribe directamente en las tablas del módulo porque es una
     * siembra de demostración; las fechas se calculan respecto a hoy. Idempotente: no hace nada si el tenant ya tiene planes.
     * Depende de UsuariosInicialesSeeder, CompetenciasSeeder, PuestosSeeder, PersonalSeeder y PlanFormacionSeeder.
     */
    public function run(): void
    {
        $tenantId = 1;
        if (PdiModel::query()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $admin = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
        $competencias = array_values(array_map(fn (array $c) => (int) $c['id'], EstructuraFacade::getCompetenciasActivas()));
        if (count($competencias) < 3) {
            // Entornos sin comportamientos ni preguntas registrados: se usan todas las competencias existentes.
            $competencias = array_values(array_map(fn (array $c) => (int) $c['id'], EstructuraFacade::getTodasLasCompetencias()));
        }
        $puestos = [];
        foreach (EstructuraFacade::getPuestosActivos() as $p) {
            $puestos[(string) $p['nombre']] = (int) $p['id'];
        }
        $ficha = function (string $tipo, string $numero) use ($tenantId): ?int {
            $f = PersonalFacade::buscarPorIdentificacion($tenantId, $tipo, $numero);

            return $f === null ? null : (int) $f['id'];
        };
        $ana = $ficha('Cedula', '0102030405');
        $luis = $ficha('Cedula', '0912345678');
        $maria = $ficha('Pasaporte', 'AB123');
        $jefe = $puestos['Jefe de talento humano'] ?? null;
        if ($admin === null || count($competencias) < 3 || $ana === null || $luis === null || $maria === null || $jefe === null) {
            return;
        }

        $admin = (int) $admin;
        $hoy = CarbonImmutable::today();
        $dia = fn (int $desplazamiento): string => $hoy->addDays($desplazamiento)->toDateString();

        // Acción del Plan de Formación (Aprobado o Ejecutando) donde María participa, si PlanFormacionSeeder la sembró.
        $planAccionId = DB::table('plan_acciones')
            ->join('planes', 'planes.id', '=', 'plan_acciones.plan_id')
            ->join('plan_accion_participantes', 'plan_accion_participantes.plan_accion_id', '=', 'plan_acciones.id')
            ->where('planes.tenant_id', $tenantId)->whereIn('planes.estado', ['Aprobado', 'Ejecutando'])
            ->where('plan_accion_participantes.colaborador_id', $maria)
            ->orderBy('plan_acciones.id')->value('plan_acciones.id');

        // 1. Mejora en Borrador sin acuerdo (Ana).
        $p1 = $this->plan($tenantId, $admin, 1, $ana, 'Mejora', 'Cerrar las brechas de comunicación detectadas en la última evaluación.', null, 'Borrador', $dia(-10), $dia(170));
        $c = $this->compromiso($p1, $tenantId, 1, 'Competencia', $competencias[0], null, 'C', 'B', 'Evaluación 360 ≥ B', $dia(-5), $dia(120));
        $this->accion($c, $tenantId, $admin, 1, 'Curso', 'Taller de comunicación efectiva', 'Equipo de Formación', $dia(0), $dia(60), null, 'Pendiente');
        $this->accion($c, $tenantId, $admin, 2, 'Coaching', 'Sesiones de coaching con la jefatura', 'Marta Ríos', $dia(10), $dia(100), null, 'Pendiente');
        $this->historial($p1, $tenantId, $admin, 'Creacion', null, 'Borrador', null, 10);

        // 2. Carrera en Borrador con el acuerdo del responsable (Luis), pendiente de que él lo acepte.
        $p2 = $this->plan($tenantId, $admin, 2, $luis, 'Carrera', 'Prepararse para asumir la jefatura de talento humano.', $jefe, 'Borrador', $dia(-7), $dia(250), ['acuerdo_responsable_at' => $hoy->subDays(2), 'acuerdo_responsable_by' => $admin, 'version' => 2]);
        $c = $this->compromiso($p2, $tenantId, 1, 'Competencia', $competencias[1], null, 'C', 'A', 'Evaluación de la competencia en el nivel A', $dia(0), $dia(200));
        $this->accion($c, $tenantId, $admin, 1, 'Mentoring', 'Mentoring con la jefa de talento humano', 'Marta Ríos', $dia(5), $dia(180), null, 'Pendiente');
        $c = $this->compromiso($p2, $tenantId, 2, 'Experiencia', null, 'Liderar un proyecto interdepartamental', null, null, 'Proyecto entregado', $dia(30), $dia(230));
        $this->accion($c, $tenantId, $admin, 1, 'NuevaResponsabilidad', 'Coordinar el comité de capacitación', 'Luis', $dia(30), $dia(220), null, 'Pendiente');
        $this->historial($p2, $tenantId, $admin, 'Creacion', null, 'Borrador', null, 7);
        $this->historial($p2, $tenantId, $admin, 'Acuerdo', null, 'Confirmado por el responsable', null, 2);

        // 3. Talento Acordado con una acción vinculada al Plan de Formación y un hito vencido (María).
        $p3 = $this->plan($tenantId, $admin, 3, $maria, 'Talento', 'Programa de Talentos: preparar el relevo del área de talento humano.', $jefe, 'Acordado', $dia(-60), $dia(300), [
            'acuerdo_responsable_at' => $hoy->subDays(40), 'acuerdo_responsable_by' => $admin, 'aceptacion_colaborador_at' => $hoy->subDays(38), 'version' => 4,
        ]);
        $c = $this->compromiso($p3, $tenantId, 1, 'Competencia', $competencias[2], null, 'D', 'B', 'Evaluación de desempeño ≥ B', $dia(-50), $dia(150));
        $this->accion($c, $tenantId, $admin, 1, 'Curso', 'Curso de gestión de equipos', 'Equipo de Formación', $dia(-30), $dia(40), $planAccionId === null ? null : (int) $planAccionId, 'EnCurso');
        $this->accion($c, $tenantId, $admin, 2, 'Autodesarrollo', 'Lectura guiada y micro-learning', null, $dia(-45), $dia(-10), null, 'Cumplida');
        $this->accion($c, $tenantId, $admin, 3, 'Codesarrollo', 'Grupo de codesarrollo de líderes', 'Roberto Salgado', $dia(10), $dia(120), null, 'Pendiente');
        $c = $this->compromiso($p3, $tenantId, 2, 'Meta', null, 'Presentar una propuesta de mejora de procesos de selección', null, null, 'Propuesta aprobada por la gerencia', $dia(-20), $dia(200));
        $this->accion($c, $tenantId, $admin, 1, 'Otra', 'Diagnóstico del proceso actual', 'María', $dia(-20), $dia(30), null, 'EnCurso');
        PdiHitoModel::query()->create(['tenant_id' => $tenantId, 'pdi_id' => $p3->id, 'fecha' => $dia(-15), 'descripcion' => 'Primera revisión de avance', 'estado' => 'Pendiente']);
        PdiHitoModel::query()->create(['tenant_id' => $tenantId, 'pdi_id' => $p3->id, 'fecha' => $dia(-40), 'descripcion' => 'Inicio del programa', 'estado' => 'Realizado', 'observaciones' => 'Reunión de arranque realizada.', 'registrado_by' => $admin, 'registrado_at' => $hoy->subDays(40)]);
        PdiHitoModel::query()->create(['tenant_id' => $tenantId, 'pdi_id' => $p3->id, 'fecha' => $dia(90), 'descripcion' => 'Revisión intermedia con el responsable', 'estado' => 'Pendiente']);
        $this->historial($p3, $tenantId, $admin, 'Creacion', null, 'Borrador', null, 60);
        $this->historial($p3, $tenantId, $admin, 'Acuerdo', null, 'Confirmado por el responsable', null, 40);
        $this->historial($p3, $tenantId, $admin, 'Aceptacion', null, 'Aceptado por el colaborador', null, 38);
        $this->historial($p3, $tenantId, $admin, 'Estado', 'Borrador', 'Acordado', null, 38);

        // 4. Mejora Cerrado «Brecha cerrada» (Luis).
        $p4 = $this->plan($tenantId, $admin, 4, $luis, 'Mejora', 'Reforzar el trabajo en equipo en el área.', null, 'Cerrado', $dia(-300), $dia(-120), [
            'acuerdo_responsable_at' => $hoy->subDays(295), 'acuerdo_responsable_by' => $admin, 'aceptacion_colaborador_at' => $hoy->subDays(293),
            'resultado' => 'BrechaCerrada', 'conclusion' => 'Alcanzó el nivel esperado y lidera ahora las reuniones del área.', 'cerrado_at' => $hoy->subDays(115), 'cerrado_by' => $admin, 'version' => 6,
        ]);
        $c = $this->compromiso($p4, $tenantId, 1, 'Competencia', $competencias[0], null, 'C', 'B', 'Evaluación 360 ≥ B', $dia(-290), $dia(-130), 'Cerrada');
        $this->accion($c, $tenantId, $admin, 1, 'Curso', 'Taller de trabajo en equipo', 'Equipo de Formación', $dia(-280), $dia(-200), null, 'Cumplida');
        $this->accion($c, $tenantId, $admin, 2, 'Coaching', 'Coaching grupal', 'Marta Ríos', $dia(-270), $dia(-150), null, 'Cumplida');
        $this->historial($p4, $tenantId, $admin, 'Creacion', null, 'Borrador', null, 300);
        $this->historial($p4, $tenantId, $admin, 'Estado', 'Borrador', 'Acordado', null, 293);
        $this->historial($p4, $tenantId, $admin, 'Cierre', 'Acordado', 'Cerrado', 'Brecha cerrada', 115);

        // 5. Carrera Cerrado «Parcial» (Ana).
        $p5 = $this->plan($tenantId, $admin, 5, $ana, 'Carrera', 'Preparar el paso a analista senior.', $jefe, 'Cerrado', $dia(-320), $dia(-100), [
            'acuerdo_responsable_at' => $hoy->subDays(315), 'acuerdo_responsable_by' => $admin, 'aceptacion_colaborador_at' => $hoy->subDays(314),
            'resultado' => 'Parcial', 'conclusion' => 'Cerró la brecha técnica; la de liderazgo continúa en un nuevo plan.', 'cerrado_at' => $hoy->subDays(95), 'cerrado_by' => $admin, 'version' => 7,
        ]);
        $c = $this->compromiso($p5, $tenantId, 1, 'Competencia', $competencias[1], null, 'D', 'B', 'Evaluación técnica ≥ B', $dia(-310), $dia(-110), 'Cerrada');
        $this->accion($c, $tenantId, $admin, 1, 'Mentoring', 'Mentoring técnico', 'Roberto Salgado', $dia(-300), $dia(-120), null, 'Cumplida');
        $c = $this->compromiso($p5, $tenantId, 2, 'Experiencia', null, 'Dirigir un equipo de tres personas', null, null, 'Equipo dirigido durante un trimestre', $dia(-300), $dia(-110), 'NoCerrada');
        $this->accion($c, $tenantId, $admin, 1, 'NuevaResponsabilidad', 'Dirigir temporalmente un equipo', 'Ana', $dia(-290), $dia(-150), null, 'Cancelada');
        $this->historial($p5, $tenantId, $admin, 'Creacion', null, 'Borrador', null, 320);
        $this->historial($p5, $tenantId, $admin, 'Estado', 'Borrador', 'Acordado', null, 314);
        $this->historial($p5, $tenantId, $admin, 'Cierre', 'Acordado', 'Cerrado', 'Parcial', 95);

        // Planes adicionales para que las pantallas se vean pobladas: otras personas con planes en distintos estados y avances.
        $otras = DB::table('colaboradores')->where('tenant_id', $tenantId)->whereNotIn('id', [$ana, $luis, $maria])->orderBy('id')->limit(7)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $variantes = [
            ['Mejora', 'Acordado', 'Fortalecer la planificación y el seguimiento de tareas.', 'Planificación', ['Cumplida', 'EnCurso'], -45, 200, false],
            ['Mejora', 'Acordado', 'Mejorar la gestión del tiempo en proyectos simultáneos.', 'Gestión del tiempo', ['EnCurso', 'Pendiente'], -30, 180, true],
            ['Carrera', 'Acordado', 'Prepararse para un puesto de coordinación.', 'Coordinación de equipos', ['Cumplida', 'Cumplida', 'Pendiente'], -90, 150, false],
            ['Mejora', 'Borrador', 'Cerrar brechas de atención al cliente.', 'Atención al cliente', ['Pendiente'], -3, 220, false],
            ['Talento', 'Borrador', 'Programa de Talentos: formación en liderazgo.', 'Liderazgo', ['Pendiente', 'Pendiente'], -5, 330, false],
            ['Mejora', 'Cerrado', 'Reforzar la orientación a resultados.', 'Orientación a resultados', ['Cumplida'], -260, -80, false],
            ['Carrera', 'Cerrado', 'Preparar el paso a jefatura de área.', 'Jefatura de área', ['Cumplida', 'Cancelada'], -280, -90, false],
        ];
        foreach ($variantes as $i => [$tipo, $estado, $objetivo, $tema, $estados, $desde, $hasta, $conVencido]) {
            if (! isset($otras[$i])) {
                break;
            }
            $extra = [];
            if ($estado !== 'Borrador' || $tipo === 'Talento') {
                $extra += ['acuerdo_responsable_at' => $hoy->subDays(max(1, abs($desde) - 3)), 'acuerdo_responsable_by' => $admin];
            }
            if ($estado !== 'Borrador') {
                $extra += ['aceptacion_colaborador_at' => $hoy->subDays(max(1, abs($desde) - 5)), 'version' => 4];
            }
            if ($estado === 'Cerrado') {
                $extra += ['resultado' => $tipo === 'Mejora' ? 'NoCerrada' : 'BrechaCerrada', 'conclusion' => 'Cierre de demostración.', 'cerrado_at' => $hoy->addDays($hasta + 5), 'cerrado_by' => $admin];
            }
            $plan = $this->plan($tenantId, $admin, 6 + $i, $otras[$i], $tipo, $objetivo, $tipo === 'Mejora' ? null : $jefe, $estado, $dia($desde), $dia($hasta), $extra);
            $compromiso = $this->compromiso($plan, $tenantId, 1, 'Competencia', $competencias[$i % count($competencias)], null, 'C', 'B', $tema.' en nivel B', $dia($desde + 5), $dia($hasta - 5), $estado === 'Cerrado' ? ($tipo === 'Mejora' ? 'NoCerrada' : 'Cerrada') : null);
            $tipos = ['Curso', 'Mentoring', 'Coaching', 'Autodesarrollo'];
            foreach ($estados as $j => $estadoAccion) {
                $this->accion($compromiso, $tenantId, $admin, $j + 1, $tipos[($i + $j) % 4], $tema.': actividad '.($j + 1), 'Equipo de Talento Humano', $dia($desde + 10 + $j * 20), $dia($desde + 40 + $j * 25), null, $estadoAccion);
            }
            PdiHitoModel::query()->create(['tenant_id' => $tenantId, 'pdi_id' => $plan->id, 'fecha' => $dia($desde + 30), 'descripcion' => 'Revisión de avance', 'estado' => $conVencido || $estado === 'Borrador' ? 'Pendiente' : 'Realizado']);
            $this->historial($plan, $tenantId, $admin, 'Creacion', null, 'Borrador', null, abs($desde));
        }
    }

    /** @param array<string, mixed> $extra */
    private function plan(int $tenantId, int $admin, int $numero, int $colaborador, string $tipo, string $objetivo, ?int $puesto, string $estado, string $inicio, string $fin, array $extra = []): PdiModel
    {
        return PdiModel::query()->create($extra + [
            'tenant_id' => $tenantId, 'codigo' => sprintf('PDI-%04d', $numero), 'colaborador_id' => $colaborador, 'tipo' => $tipo, 'objetivo' => $objetivo,
            'puesto_objetivo_id' => $puesto, 'responsable_id' => $admin, 'fecha_inicio' => $inicio, 'fecha_fin' => $fin, 'estado' => $estado, 'version' => 1,
            'created_by' => $admin, 'updated_by' => $admin,
        ]);
    }

    private function compromiso(PdiModel $plan, int $tenantId, int $orden, string $dimension, ?int $elemento, ?string $descripcion, ?string $actual, ?string $objetivo, ?string $indicador, string $inicio, string $fin, ?string $resultado = null): PdiCompromisoModel
    {
        return PdiCompromisoModel::query()->create([
            'tenant_id' => $tenantId, 'pdi_id' => $plan->id, 'orden' => $orden, 'dimension' => $dimension,
            'tipo_elemento' => $elemento === null ? null : 'Competencia', 'elemento_id' => $elemento, 'descripcion' => $descripcion,
            'nivel_actual' => $actual, 'nivel_objetivo' => $objetivo, 'indicador' => $indicador, 'fecha_inicio' => $inicio, 'fecha_fin' => $fin, 'resultado' => $resultado,
        ]);
    }

    private function accion(PdiCompromisoModel $compromiso, int $tenantId, int $admin, int $orden, string $tipo, string $descripcion, ?string $persona, string $inicio, string $fin, ?int $planAccionId, string $estado): void
    {
        PdiAccionModel::query()->create([
            'tenant_id' => $tenantId, 'compromiso_id' => $compromiso->id, 'orden' => $orden, 'tipo' => $tipo, 'descripcion' => $descripcion, 'persona_cargo' => $persona,
            'fecha_inicio' => $inicio, 'fecha_fin' => $fin, 'plan_accion_id' => $planAccionId, 'estado' => $estado,
            'estado_congelado' => $planAccionId !== null && $compromiso->resultado !== null, 'created_by' => $admin, 'updated_by' => $admin,
        ]);
    }

    private function historial(PdiModel $plan, int $tenantId, int $admin, string $tipo, ?string $anterior, ?string $nuevo, ?string $motivo, int $haceDias): void
    {
        $renglon = PdiHistorialModel::query()->create([
            'tenant_id' => $tenantId, 'pdi_id' => $plan->id, 'tipo' => $tipo, 'valor_anterior' => $anterior, 'valor_nuevo' => $nuevo, 'motivo' => $motivo, 'user_id' => $admin,
        ]);
        PdiHistorialModel::query()->whereKey($renglon->id)->toBase()->update(['created_at' => CarbonImmutable::now()->subDays($haceDias)]);
    }
}
