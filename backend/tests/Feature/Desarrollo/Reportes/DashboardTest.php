<?php

beforeEach(function () {
    // Hoy = 2026-10-17: la acción por defecto (2026-03-02 a 2026-03-13, 16 horas, costo 800) ya está Finalizada.
    $this->falsos = actividadFalsos(['hoy' => '2026-10-17', 'vigentes' => [10 => 'Ana', 11 => 'Luis', 12 => 'Eva', 13 => 'Pedro', 14 => 'Rita']]);
    departamentosActuales([10 => 1, 11 => 1, 12 => 2, 13 => 2]);
    $this->dashboard = fn (array $filtros = []) => $this->getJson('/api/reportes/dashboard?'.http_build_query($filtros));
});

it('exige sesión y rol de administrador', function () {
    ($this->dashboard)()->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    ($this->dashboard)()->assertForbidden();
});

it('usa el año actual por defecto y entrega los indicadores con su forma', function () {
    actuarComoAdminDeTenant();
    actividadConAsistencia([10 => 'Presente', 11 => 'Presente', 12 => 'Ausente']);

    ($this->dashboard)()->assertOk()
        ->assertJsonPath('data.periodo', ['desde' => '2026-01-01', 'hasta' => '2026-12-31'])
        ->assertJsonPath('data.periodo_anterior', ['desde' => '2025-01-01', 'hasta' => '2025-12-31'])
        ->assertJsonPath('data.fuentes_no_disponibles', [])
        ->assertJsonPath('data.indicadores.plan_cumplido.valor', 100)
        ->assertJsonPath('data.indicadores.plan_cumplido.acciones_finalizadas', 1)
        ->assertJsonPath('data.indicadores.plan_cumplido.acciones_validas', 1)
        ->assertJsonPath('data.indicadores.total_horas.valor', 32)
        ->assertJsonPath('data.indicadores.total_horas.horas_actividades', 32)
        ->assertJsonPath('data.indicadores.total_horas.horas_microlearning', 0)
        ->assertJsonPath('data.indicadores.satisfaccion.valor', null)
        ->assertJsonPath('data.indicadores.brechas_cerradas.valor', null)
        ->assertJsonPath('data.indicadores.roi_estimado.valor', null)
        ->assertJsonPath('data.gestion.inversion_por_colaborador.valor', 400)
        ->assertJsonPath('data.gestion.horas_por_colaborador.valor', 16)
        ->assertJsonPath('data.gestion.costo_por_hora.valor', 25)
        ->assertJsonCount(7, 'data.graficos.distribucion_por_tipo.datos')
        ->assertJsonCount(12, 'data.graficos.evolucion_brechas.datos');
});

it('sin datos en el período anterior la comparación va sin valor', function () {
    actuarComoAdminDeTenant();
    actividadConAsistencia([10 => 'Presente']);

    $r = ($this->dashboard)()->assertOk();

    expect($r->json('data.indicadores.total_horas.anterior'))->toBeNull()
        ->and($r->json('data.indicadores.total_horas.diferencia'))->toBeNull()
        ->and($r->json('data.indicadores.total_horas.variacion_pct'))->toBeNull();
});

it('compara con el año anterior cuando hay datos en ambos períodos', function () {
    actuarComoAdminDeTenant();
    actividadConAsistencia([10 => 'Presente', 11 => 'Presente']);
    [$plan, $accion] = accionEnPlan(['fecha_inicio' => '2025-03-02', 'fecha_fin' => '2025-03-13', 'colaborador_ids' => [10]], 'Ejecutando', ['anio' => 2025]);
    $act = actividadDe($accion);
    registroDeAsistencia($act['id'], 10, 'Presente');

    $r = ($this->dashboard)()->assertOk();

    expect($r->json('data.indicadores.total_horas.valor'))->toEqual(32)
        ->and($r->json('data.indicadores.total_horas.anterior'))->toEqual(16)
        ->and($r->json('data.indicadores.total_horas.diferencia'))->toEqual(16)
        ->and($r->json('data.indicadores.total_horas.variacion_pct'))->toEqual(100);
});

it('calcula satisfacción, ROI y horas de micro-learning', function () {
    actuarComoAdminDeTenant();
    $a = actividadConAsistencia([10 => 'Presente', 11 => 'Presente']);
    encuestaDe($a['actividad'], 10, 5);
    encuestaDe($a['actividad'], 11, 4);
    encuestaDe($a['actividad'], 99, 1, tenantId: 2);
    impactoDeAccion($a['accion'], 2400);
    progresoMicro(microDe(['duracion_minutos' => 90])->id, 10, 'Completado');

    ($this->dashboard)()->assertOk()
        ->assertJsonPath('data.indicadores.satisfaccion.valor', 4.5)
        ->assertJsonPath('data.indicadores.satisfaccion.respuestas', 2)
        ->assertJsonPath('data.indicadores.roi_estimado.valor', 200)
        ->assertJsonPath('data.indicadores.roi_estimado.acciones_evaluadas', 1)
        ->assertJsonPath('data.indicadores.total_horas.horas_microlearning', 1.5)
        ->assertJsonPath('data.indicadores.total_horas.valor', 33.5);
});

it('calcula brechas cerradas y detectadas con la regla de las DNF', function () {
    actuarComoAdminDeTenant();
    dnfEvaluada('2026-01-10', 10, 3, 'ConBrecha', 'D');
    dnfEvaluada('2026-06-10', 10, 3, 'Cumple', 'B');
    dnfEvaluada('2026-02-10', 12, 3, 'ConBrecha', 'C');

    $r = ($this->dashboard)()->assertOk();

    expect($r->json('data.indicadores.brechas_cerradas.valor'))->toEqual(1)
        ->and($r->json('data.indicadores.brechas_cerradas.detectadas'))->toBe(2)
        ->and($r->json('data.indicadores.brechas_cerradas.anterior'))->toEqual(0)
        ->and($r->json('data.indicadores.brechas_cerradas.diferencia'))->toEqual(1)
        ->and($r->json('data.indicadores.brechas_cerradas.variacion_pct'))->toBeNull()
        ->and($r->json('data.graficos.evolucion_brechas.datos.5'))->toBe(['mes' => '2026-06', 'activas' => 1, 'cerradas' => 1]);
});

it('con el filtro por departamento recalcula con sus participantes', function () {
    actuarComoAdminDeTenant();
    actividadConAsistencia([10 => 'Presente', 11 => 'Presente', 12 => 'Presente', 13 => 'Ausente']);

    ($this->dashboard)(['departamento_id' => 2])->assertOk()
        ->assertJsonPath('data.indicadores.total_horas.valor', 16)
        ->assertJsonPath('data.gestion.inversion_por_colaborador.valor', 266.67)
        ->assertJsonPath('data.graficos.horas_por_departamento.datos.0.departamento_id', 2);
});

it('con el filtro de tipo Microlearning solo traen valores horas y distribución', function () {
    actuarComoAdminDeTenant();
    actividadConAsistencia([10 => 'Presente']);
    progresoMicro(microDe(['duracion_minutos' => 60])->id, 10, 'Completado');

    ($this->dashboard)(['tipo' => 'Microlearning'])->assertOk()
        ->assertJsonPath('data.indicadores.total_horas.valor', 1)
        ->assertJsonPath('data.indicadores.plan_cumplido.valor', null)
        ->assertJsonPath('data.indicadores.satisfaccion.valor', null);
});

it('las brechas cerradas ignoran el tipo y el plan', function () {
    actuarComoAdminDeTenant();
    dnfEvaluada('2026-01-10', 10, 3, 'ConBrecha', 'D');
    dnfEvaluada('2026-06-10', 10, 3, 'Cumple', 'B');

    ($this->dashboard)(['tipo' => 'Virtual', 'plan_id' => 999])->assertOk()
        ->assertJsonPath('data.indicadores.brechas_cerradas.valor', 1);
});

it('responde con una fuente caída: informa cuál y deja el resto del dashboard', function () {
    actuarComoAdminDeTenant();
    actividadConAsistencia([10 => 'Presente']);
    departamentosActualesCaidos();

    ($this->dashboard)()->assertOk()
        ->assertJsonPath('data.fuentes_no_disponibles', ['personal'])
        ->assertJsonPath('data.graficos.horas_por_departamento.disponible', false)
        ->assertJsonPath('data.indicadores.plan_cumplido.disponible', true)
        ->assertJsonPath('data.indicadores.plan_cumplido.valor', 100);

    ($this->dashboard)(['departamento_id' => 1])->assertOk()
        ->assertJsonPath('data.indicadores.plan_cumplido.disponible', false)
        ->assertJsonPath('data.indicadores.plan_cumplido.valor', null);
});

it('rechaza un rango inválido con el código periodo_invalido', function () {
    actuarComoAdminDeTenant();

    ($this->dashboard)(['fecha_desde' => '2026-05-01', 'fecha_hasta' => '2026-04-01'])->assertUnprocessable()
        ->assertJsonPath('code', 'periodo_invalido');
});

it('valida el formato de fechas, el tipo y los ids', function () {
    actuarComoAdminDeTenant();

    ($this->dashboard)(['fecha_desde' => '01/05/2026'])->assertUnprocessable()->assertJsonValidationErrors('fecha_desde');
    ($this->dashboard)(['tipo' => 'Inventado'])->assertUnprocessable()->assertJsonValidationErrors('tipo');
    ($this->dashboard)(['departamento_id' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('departamento_id');
});

it('aísla los datos por tenant', function () {
    actuarComoAdminDeTenant(2);

    ($this->dashboard)()->assertOk()
        ->assertJsonPath('data.indicadores.plan_cumplido.valor', null)
        ->assertJsonPath('data.indicadores.total_horas.valor', null);
});

it('entrega las opciones de los filtros: departamentos activos y planes en ejecución o cerrados', function () {
    actuarComoAdminDeTenant();
    [$plan] = accionEnPlan();
    accionEnPlan([], 'Borrador');

    $r = $this->getJson('/api/reportes/opciones')->assertOk();

    expect(array_column($r->json('data.departamentos'), 'nombre'))->toBe(['Tecnología', 'Talento Humano'])
        ->and(array_column($r->json('data.planes'), 'id'))->toBe([$plan])
        ->and($r->json('data.planes.0'))->toHaveKeys(['id', 'codigo', 'nombre', 'anio', 'estado']);

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/reportes/opciones')->assertForbidden();
});
