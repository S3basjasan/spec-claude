<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

afterEach(fn () => Carbon::setTestNow());

/**
 * Salida formalizada con el Colaborador autenticado (su cuenta está asociada a la ficha).
 *
 * @return array{id: int, ficha: mixed, vinculo: int, tareas: list<array<string, mixed>>}
 */
function salidaConColaborador(mixed $prueba): array
{
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($prueba);
    actuarComoColaboradorDe($salida['ficha']);

    return $salida;
}

function guardarEntrevistaApi(mixed $prueba, array $cuerpo, ?string $clave = null)
{
    return $prueba->putJson(URL_MI_DESVINCULACION.'/entrevista', $cuerpo, ['Idempotency-Key' => $clave ?? (string) Str::uuid()]);
}

function accionDeEntrevistaApi(mixed $prueba, string $accion, int $version, ?string $clave = null)
{
    return $prueba->postJson(URL_MI_DESVINCULACION."/entrevista/{$accion}", ['version' => $version], ['Idempotency-Key' => $clave ?? (string) Str::uuid()]);
}

it('el Colaborador ve su desvinculación comunicada y la entrevista como una tarea más, sin datos administrativos (CA-10, HU-06)', function () {
    $salida = salidaConColaborador($this);
    $vista = $this->getJson(URL_MI_DESVINCULACION)->assertOk()->json('data');

    expect($vista['motivo']['clave'])->toBe('Renuncia')
        ->and($vista['fecha_efectiva'])->toBe(hoyDelTenant(10))
        ->and($vista['estado'])->toBe('Programada')
        ->and(collect($vista['tareas'])->pluck('nombre')->all())->toBe(['Devolver equipos', 'Cerrar accesos', 'Entrevista de salida'])
        ->and($vista['entrevista']['estado'])->toBe('Pendiente')
        ->and($vista['entrevista']['responder_disponible'])->toBeTrue()
        ->and(array_keys($vista))->not->toContain('historial', 'respaldos', 'politica', 'aplicacion', 'cierre', 'formalizado')
        ->and(array_keys($vista['tareas'][0]))->not->toContain('respaldos', 'exige_evidencia', 'completada_por');
    expect($salida['id'])->toBe((int) $this->getJson(URL_MI_DESVINCULACION)->json('data.id'));
});

it('sin ficha asociada o sin salida formalizada responde 404', function () {
    politicaVigente('Offboarding');
    actuarComoUsuarioDeTenant();
    $this->getJson(URL_MI_DESVINCULACION)->assertStatus(404)->assertJsonPath('code', 'SIN_FICHA_ASOCIADA');

    ['ficha' => $ficha] = fichaConVinculo();
    actuarComoColaboradorDe($ficha);
    $this->getJson(URL_MI_DESVINCULACION)->assertStatus(404)->assertJsonPath('code', 'EXPEDIENTE_NO_ENCONTRADO');
    $this->getJson(URL_MI_DESVINCULACION.'/entrevista')->assertStatus(404)->assertJsonPath('code', 'EXPEDIENTE_NO_ENCONTRADO');
});

it('un Colaborador solo ve su propia desvinculación, nunca la de otros ni la de otro tenant', function () {
    politicaVigente('Offboarding');
    politicaVigente('Offboarding', [], 2);
    $propia = salidaFormalizada($this);
    salidaFormalizada($this, [], null, 2);
    actuarComoColaboradorDe($propia['ficha']);

    expect($this->getJson(URL_MI_DESVINCULACION)->json('data.id'))->toBe($propia['id']);
    $this->getJson(URL_OFFBOARDING.'/'.$propia['id'])->assertForbidden();
});

it('guarda avance sin presentar: pasa a «en curso», conserva lo respondido y omite lo no respondido (CA-12, CA-13)', function () {
    salidaConColaborador($this);

    $guardada = guardarEntrevistaApi($this, respuestasDeEntrevista())->assertOk();

    expect($guardada->json('data.estado'))->toBe('EnCurso')
        ->and($guardada->json('data.cerrada'))->toBeFalse()
        ->and($guardada->json('data.version'))->toBe(2)
        ->and($guardada->json('data.respuestas.valoraciones'))->toBe(['DIM-DESARROLLO' => 2, 'DIM-SUPERVISION' => 4, 'DIM-GENERAL' => 3])
        ->and($guardada->json('data.respuestas.categorias'))->toBe(['CAT-DESARROLLO', 'CAT-COMPENSACION'])
        ->and($guardada->json('data.respuestas.abiertas.ABI-01'))->toBe('Busqué crecer y no había una ruta clara.')
        ->and($guardada->json('data.cuestionario.dimensiones'))->toHaveCount(6)
        ->and($guardada->json('data.cuestionario.categorias'))->toHaveCount(8)
        ->and($guardada->json('data.cuestionario.abiertas'))->toHaveCount(6);

    // Las preguntas sin responder no tienen fila: ausencia, nunca un 0.
    expect(DB::table('offboarding_respuestas')->count())->toBe(7)
        ->and(DB::table('offboarding_respuestas')->where('valor', 0)->count())->toBe(0)
        ->and(DB::table('offboarding_respuestas')->where('pregunta_id', 'DIM-CARGA')->exists())->toBeFalse()
        ->and(DB::table('offboarding_entrevistas')->value('presentada_en'))->toBeNull();
});

it('guardar de nuevo reemplaza el avance y permite vaciar una respuesta', function () {
    salidaConColaborador($this);
    guardarEntrevistaApi($this, respuestasDeEntrevista())->assertOk();

    $segunda = guardarEntrevistaApi($this, ['version' => 2, 'valoraciones' => ['DIM-GENERAL' => 5], 'categorias' => [], 'abiertas' => ['ABI-02' => 'Buen equipo']])->assertOk();

    expect($segunda->json('data.respuestas.valoraciones'))->toBe(['DIM-GENERAL' => 5])
        ->and($segunda->json('data.respuestas.categorias'))->toBe([])
        ->and($segunda->json('data.respuestas.abiertas'))->toBe(['ABI-02' => 'Buen equipo'])
        ->and(DB::table('offboarding_respuestas')->count())->toBe(2);
});

it('valida la escala 1–5, las preguntas y categorías del cuestionario y la longitud del texto (CA-13)', function () {
    salidaConColaborador($this);

    foreach ([0, 6, 3.5, 'alto'] as $valor) {
        guardarEntrevistaApi($this, ['version' => 1, 'valoraciones' => ['DIM-GENERAL' => $valor]])->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    }
    guardarEntrevistaApi($this, ['version' => 1, 'valoraciones' => ['DIM-INVENTADA' => 3]])->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    guardarEntrevistaApi($this, ['version' => 1, 'categorias' => ['CAT-INVENTADA']])->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    guardarEntrevistaApi($this, ['version' => 1, 'abiertas' => ['ABI-99' => 'x']])->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    guardarEntrevistaApi($this, ['version' => 1, 'abiertas' => ['ABI-01' => str_repeat('a', 2001)]])->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    guardarEntrevistaApi($this, ['version' => 1, 'tenant_id' => 2])->assertStatus(422)->assertJsonPath('code', 'CAMPO_NO_PERMITIDO');
    guardarEntrevistaApi($this, ['valoraciones' => ['DIM-GENERAL' => 3]])->assertStatus(422);

    expect(DB::table('offboarding_respuestas')->count())->toBe(0);
});

it('todas las preguntas son opcionales: una valoración nula o un texto vacío se tratan como no proporcionados', function () {
    salidaConColaborador($this);

    guardarEntrevistaApi($this, ['version' => 1, 'valoraciones' => ['DIM-GENERAL' => null, 'DIM-CARGA' => ''], 'abiertas' => ['ABI-01' => '   ', 'ABI-02' => null]])->assertOk();

    expect(DB::table('offboarding_respuestas')->count())->toBe(0);
});

it('el texto abierto se conserva tal como lo escribió el colaborador, sin interpretarlo (CA-14)', function () {
    salidaConColaborador($this);
    $texto = "Mi jefe\ncambió tres veces — «y no hubo feedback». <b>100%</b> & más";

    guardarEntrevistaApi($this, ['version' => 1, 'abiertas' => ['ABI-03' => $texto]])->assertOk();

    expect(DB::table('offboarding_respuestas')->where('pregunta_id', 'ABI-03')->value('texto'))->toBe($texto);
});

it('confirmar presenta la entrevista, resuelve su tarea y la vuelve inmutable (CA-11, CA-15)', function () {
    salidaConColaborador($this);
    guardarEntrevistaApi($this, respuestasDeEntrevista())->assertOk();

    $completada = accionDeEntrevistaApi($this, 'confirmacion', 2)->assertOk();

    expect($completada->json('data.estado'))->toBe('Completada')
        ->and($completada->json('data.cerrada'))->toBeTrue()
        ->and($completada->json('data.presentada_en'))->not->toBeNull()
        ->and($this->getJson(URL_MI_DESVINCULACION)->json('data.entrevista.responder_disponible'))->toBeFalse()
        ->and(collect($this->getJson(URL_MI_DESVINCULACION)->json('data.tareas'))->firstWhere('tipo', 'Entrevista')['estado'])->toBe('Completada');

    guardarEntrevistaApi($this, respuestasDeEntrevista(3))->assertStatus(409)->assertJsonPath('code', 'ENTREVISTA_CERRADA');
    accionDeEntrevistaApi($this, 'declinacion', 3)->assertStatus(409)->assertJsonPath('code', 'ENTREVISTA_CERRADA');
    accionDeEntrevistaApi($this, 'confirmacion', 3)->assertStatus(409)->assertJsonPath('code', 'ENTREVISTA_CERRADA');

    // Las respuestas no cambian ni siquiera por escritura directa.
    $mensaje = '';
    try {
        DB::table('offboarding_respuestas')->update(['texto' => 'manipulado']);
        DB::table('offboarding_respuestas')->delete();
    } catch (Throwable $e) {
        $mensaje = $e->getMessage();
    }
    expect($mensaje)->toContain('ENTREVISTA')->and(DB::table('offboarding_respuestas')->count())->toBe(7);
});

it('confirmar es idempotente con la misma clave', function () {
    salidaConColaborador($this);
    $clave = (string) Str::uuid();

    accionDeEntrevistaApi($this, 'confirmacion', 1, $clave)->assertOk();
    accionDeEntrevistaApi($this, 'confirmacion', 1, $clave)->assertOk();

    expect(DB::table('offboarding_eventos')->where('evento', 'EntrevistaCompletada')->count())->toBe(1);
});

it('declinar deja la entrevista «no realizada» sin consecuencias y no impide la salida efectiva ni el cierre (CA-11, CA-16)', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this, ['fecha_efectiva' => hoyDelTenant(1)]);
    actuarComoColaboradorDe($salida['ficha']);

    $declinada = accionDeEntrevistaApi($this, 'declinacion', 1)->assertOk();
    expect($declinada->json('data.estado'))->toBe('NoRealizada')
        ->and($declinada->json('data.cerrada'))->toBeTrue();

    Carbon::setTestNow(now()->addDays(2));
    $this->artisan('offboarding:aplicar-salidas')->assertSuccessful();
    actuarComoAdminDeTenant();
    completarChecklist($this, $salida);
    $detalle = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->assertOk();

    expect($detalle->json('data.estado'))->toBe('Efectiva')
        ->and($detalle->json('data.entrevista.estado'))->toBe('NoRealizada')
        ->and($detalle->json('data.entrevista.motivo_no_realizada'))->toBe('Declinada')
        ->and($detalle->json('data.acciones.cerrar'))->toBeTrue();
    accionSalidaApi($this, $salida['id'], 'cierre', ['version' => $detalle->json('data.version')])->assertOk()->assertJsonPath('data.cierre.estado', 'Cerrado');
});

it('la desvinculación efectiva no depende de la entrevista: se aplica con la entrevista pendiente (CA-16)', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this, ['fecha_efectiva' => hoyDelTenant(1)]);
    Carbon::setTestNow(now()->addDays(2));

    $this->artisan('offboarding:aplicar-salidas')->assertSuccessful();

    expect(DB::table('offboarding_expedientes')->value('estado'))->toBe('Efectiva')
        ->and(DB::table('offboarding_entrevistas')->value('estado'))->toBe('Pendiente');
});

it('el Administrador deja la entrevista «no realizada» con un motivo del catálogo y el Colaborador deja de poder responder', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    $version = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->json('data.entrevista.version');

    accionSalidaApi($this, $salida['id'], 'entrevista/no-realizada', ['version' => $version])->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    accionSalidaApi($this, $salida['id'], 'entrevista/no-realizada', ['version' => $version, 'motivo' => 'Aburrimiento'])->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    $hecha = accionSalidaApi($this, $salida['id'], 'entrevista/no-realizada', ['version' => $version, 'motivo' => 'SinAcceso'])->assertOk();

    expect($hecha->json('data.entrevista.estado'))->toBe('NoRealizada')
        ->and($hecha->json('data.entrevista.motivo_no_realizada'))->toBe('SinAcceso')
        ->and(collect($hecha->json('data.tareas'))->firstWhere('tipo', 'Entrevista')['estado'])->toBe('Completada');

    actuarComoColaboradorDe($salida['ficha']);
    guardarEntrevistaApi($this, respuestasDeEntrevista())->assertStatus(409)->assertJsonPath('code', 'ENTREVISTA_CERRADA');
    expect($this->getJson(URL_MI_DESVINCULACION)->json('data.entrevista.responder_disponible'))->toBeFalse();
});

it('el Administrador no puede responder la entrevista en nombre del colaborador ni el Colaborador completar tareas administrativas (CA-10)', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    $accesos = tareaPorNombre($salida['tareas'], 'Cerrar accesos');

    // El Administrador sin ficha asociada no tiene entrevista propia.
    $this->putJson(URL_MI_DESVINCULACION.'/entrevista', respuestasDeEntrevista(), ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(404)->assertJsonPath('code', 'SIN_FICHA_ASOCIADA');

    actuarComoColaboradorDe($salida['ficha']);
    foreach (['cierre', 'aplicacion', 'cancelacion', "tareas/{$accesos['id']}/completar", 'entrevista/no-realizada'] as $accion) {
        accionSalidaApi($this, $salida['id'], $accion, ['version' => 1])->assertForbidden();
    }
    expect(DB::table('offboarding_tareas')->where('estado', 'Completada')->count())->toBe(0);
});

it('un Colaborador de otro colaborador no ve la entrevista ajena: cada cuenta solo llega a su ficha', function () {
    politicaVigente('Offboarding');
    $una = salidaFormalizada($this);
    $otra = salidaFormalizada($this);
    actuarComoColaboradorDe($otra['ficha']);

    guardarEntrevistaApi($this, ['version' => 1, 'abiertas' => ['ABI-01' => 'de la otra']])->assertOk();

    expect(DB::table('offboarding_respuestas')->count())->toBe(1)
        ->and(DB::table('offboarding_respuestas')->join('offboarding_entrevistas', 'offboarding_entrevistas.id', '=', 'offboarding_respuestas.entrevista_id')->value('offboarding_entrevistas.expediente_id'))->toBe($otra['id'])
        ->and($una['id'])->not->toBe($otra['id']);
});

it('una salida cancelada deja de aparecer para el Colaborador', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    accionSalidaApi($this, $salida['id'], 'cancelacion', ['version' => 1, 'razon' => 'Cancelada'])->assertOk();
    actuarComoColaboradorDe($salida['ficha']);

    $this->getJson(URL_MI_DESVINCULACION)->assertStatus(404)->assertJsonPath('code', 'EXPEDIENTE_NO_ENCONTRADO');
    guardarEntrevistaApi($this, respuestasDeEntrevista())->assertStatus(404);
});

it('una incidencia de aplicación no se muestra al Colaborador: la salida sigue figurando programada', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this, ['fecha_efectiva' => hoyDelTenant(3)]);
    DB::table('asignaciones_laborales')->where('vinculo_id', $salida['vinculo'])->update(['fecha_fin' => hoyDelTenant(1)]);
    DB::table('vinculos_laborales')->where('id', $salida['vinculo'])->update(['fecha_salida' => hoyDelTenant(1)]);
    Carbon::setTestNow(now()->addDays(4));
    $this->artisan('offboarding:aplicar-salidas')->assertSuccessful();
    actuarComoColaboradorDe($salida['ficha']);

    $vista = $this->getJson(URL_MI_DESVINCULACION)->assertOk()->json('data');

    expect($vista['estado'])->toBe('Programada')
        ->and(json_encode($vista))->not->toContain('VINCULO_NO_VIGENTE', 'incidencia');
});
