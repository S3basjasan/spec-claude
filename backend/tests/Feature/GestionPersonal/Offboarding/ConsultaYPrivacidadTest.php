<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Salida con la entrevista completada por el Colaborador; deja autenticado al Administrador.
 *
 * @param  array<string, mixed>  $respuestas
 * @return array{id: int, ficha: mixed, vinculo: int, tareas: list<array<string, mixed>>}
 */
function salidaConEntrevistaCompletada(mixed $prueba, array $respuestas = [], int $tenantId = 1, array $cambios = []): array
{
    if (! DB::table('politicas_personal')->where('tenant_id', $tenantId)->where('tipo', 'Offboarding')->exists()) {
        politicaVigente('Offboarding', [], $tenantId);
    }
    $salida = salidaFormalizada($prueba, $cambios, fichaConVinculo(['numero_identificacion' => 'C'.str_replace('.', '', uniqid('', true))], $tenantId)['ficha'], $tenantId);
    actuarComoColaboradorDe($salida['ficha']);
    $prueba->putJson(URL_MI_DESVINCULACION.'/entrevista', array_replace(respuestasDeEntrevista(), $respuestas), ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
    $prueba->postJson(URL_MI_DESVINCULACION.'/entrevista/confirmacion', ['version' => 2], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
    actuarComoAdminDeTenant($tenantId);

    return $salida;
}

it('sin el permiso específico nadie consulta las respuestas y la negativa no deja rastro de consulta (CA-17)', function () {
    $salida = salidaConEntrevistaCompletada($this);

    $this->getJson(URL_OFFBOARDING.'/'.$salida['id'].'/entrevista/respuestas')->assertForbidden()->assertJsonPath('code', 'SIN_PERMISO_DE_ENTREVISTAS');
    $this->getJson(URL_OFFBOARDING.'/'.$salida['id'].'/entrevista/estructura')->assertForbidden()->assertJsonPath('code', 'SIN_PERMISO_DE_ENTREVISTAS');

    expect(DB::table('offboarding_eventos')->where('evento', 'ConsultaDeRespuestas')->count())->toBe(0);
});

it('el detalle del expediente nunca incluye las respuestas, ni con permiso (CA-17)', function () {
    permisoDeEntrevistas(true);
    $salida = salidaConEntrevistaCompletada($this);

    $cuerpo = json_encode($this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->assertOk()->json());
    $lista = json_encode($this->getJson(URL_OFFBOARDING)->assertOk()->json());

    expect($cuerpo)->not->toContain('Busqué crecer', 'DIM-DESARROLLO', 'CAT-COMPENSACION')
        ->and($lista)->not->toContain('Busqué crecer', 'DIM-DESARROLLO');
});

it('con permiso se leen las respuestas originales de una entrevista completada y se registra cada consulta (CA-17, HU-10)', function () {
    permisoDeEntrevistas(true);
    $salida = salidaConEntrevistaCompletada($this);

    $respuesta = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'].'/entrevista/respuestas')->assertOk();

    expect($respuesta->json('data.disponible'))->toBeTrue()
        ->and($respuesta->json('data.cuestionario_version'))->toBe(1)
        ->and($respuesta->json('data.respuestas.valoraciones.DIM-DESARROLLO'))->toBe(['dimension' => 'Desarrollo profesional', 'valor' => 2])
        ->and($respuesta->json('data.respuestas.valoraciones'))->not->toHaveKey('DIM-CARGA')
        ->and(collect($respuesta->json('data.respuestas.categorias'))->pluck('id')->all())->toBe(['CAT-DESARROLLO', 'CAT-COMPENSACION'])
        ->and($respuesta->json('data.respuestas.abiertas.ABI-01'))->toBe('Busqué crecer y no había una ruta clara.')
        ->and($respuesta->json('data.respuestas.abiertas'))->not->toHaveKey('ABI-04');

    $this->getJson(URL_OFFBOARDING.'/'.$salida['id'].'/entrevista/respuestas')->assertOk();
    $consultas = DB::table('offboarding_eventos')->where('evento', 'ConsultaDeRespuestas')->get();
    expect($consultas)->toHaveCount(2)
        ->and($consultas->pluck('actor_tipo')->unique()->all())->toBe(['usuario'])
        ->and(json_encode($consultas->pluck('detalle')->all()))->not->toContain('Busqué crecer');
});

it('una entrevista pendiente, en curso o no realizada no expone ninguna respuesta al Administrador', function () {
    permisoDeEntrevistas(true);
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    actuarComoColaboradorDe($salida['ficha']);
    $this->putJson(URL_MI_DESVINCULACION.'/entrevista', respuestasDeEntrevista(), ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
    actuarComoAdminDeTenant();

    $enCurso = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'].'/entrevista/respuestas')->assertOk();
    $estructura = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'].'/entrevista/estructura')->assertOk();

    expect($enCurso->json('data.disponible'))->toBeFalse()
        ->and($enCurso->json('data.estado'))->toBe('EnCurso')
        ->and($enCurso->json('data.respuestas'))->toBeNull()
        ->and($estructura->json('data.disponible'))->toBeFalse()
        ->and($estructura->json('data.estructura'))->toBeNull()
        ->and(json_encode([$enCurso->json(), $estructura->json()]))->not->toContain('Busqué crecer');
});

it('la estructura para análisis separa contexto, cuantitativo, cualitativo y control con procedencia y sin datos identificativos (CA-18, HU-11)', function () {
    permisoDeEntrevistas(true);
    $salida = salidaConEntrevistaCompletada($this, [], 1, ['motivo' => 'Jubilacion']);

    $estructura = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'].'/entrevista/estructura')->assertOk()->json('data.estructura');

    expect($estructura['contexto']['tipo_de_desvinculacion'])->toBe('Jubilacion')
        ->and($estructura['contexto']['cuestionario_version'])->toBe(1)
        ->and($estructura['contexto']['referencia'])->toMatch('/^[0-9a-f]{64}$/')
        ->and($estructura['control']['estructura_version'])->toBe(1);

    $porPregunta = collect($estructura['cuantitativo'])->keyBy('pregunta_id');
    expect($porPregunta['DIM-DESARROLLO']['valor'])->toBe(2)
        ->and($porPregunta['DIM-DESARROLLO']['procedencia'])->toBe('declarado_por_colaborador')
        ->and($porPregunta['DIM-DESARROLLO']['escala'][1])->toBe('Muy insatisfecho')
        ->and($porPregunta['DIM-CARGA']['valor'])->toBeNull()
        ->and($porPregunta['DIM-CARGA']['procedencia'])->toBeNull()
        ->and($estructura['cualitativo']['categorias_declaradas'])->toBe(['CAT-DESARROLLO', 'CAT-COMPENSACION'])
        ->and($estructura['control']['no_respondidas'])->toContain('DIM-CARGA', 'ABI-04')
        ->and($estructura['control']['respondidas'])->toContain('DIM-DESARROLLO', 'ABI-01', 'CATEGORIAS')
        ->and($estructura['control']['completitud'])->toBeBetween(0.01, 0.99);

    $json = json_encode($estructura);
    expect($json)->not->toContain((string) $salida['ficha']->numero_identificacion)
        ->and($json)->not->toContain((string) $salida['ficha']->nombres)
        ->and($json)->not->toContain((string) $salida['ficha']->apellidos)
        ->and($json)->not->toContain('"colaborador_id"');
    expect(collect($estructura['cuantitativo'])->pluck('valor')->filter(fn ($v) => $v === 0))->toHaveCount(0);
});

it('consultar respuestas o su estructura no envía datos a ningún servicio externo (sin IA)', function () {
    permisoDeEntrevistas(true);
    $salida = salidaConEntrevistaCompletada($this);
    Http::fake();

    $this->getJson(URL_OFFBOARDING.'/'.$salida['id'].'/entrevista/estructura')->assertOk();
    $this->getJson(URL_OFFBOARDING.'/'.$salida['id'].'/entrevista/respuestas')->assertOk();
    $this->getJson(URL_OFFBOARDING.'/agregados')->assertOk();

    Http::assertNothingSent();
});

it('las respuestas de otro tenant no se consultan ni con permiso: responde como un expediente inexistente (RN aislamiento)', function () {
    permisoDeEntrevistas(true);
    $ajena = salidaConEntrevistaCompletada($this, [], 2);
    actuarComoAdminDeTenant(1);

    foreach (['respuestas', 'estructura'] as $vista) {
        $this->getJson(URL_OFFBOARDING.'/'.$ajena['id']."/entrevista/{$vista}")->assertStatus(404)->assertJsonPath('code', 'EXPEDIENTE_NO_ENCONTRADO');
    }
    expect(DB::table('offboarding_eventos')->where('evento', 'ConsultaDeRespuestas')->count())->toBe(0);
});

it('los agregados se ocultan por debajo del mínimo para no identificar a los participantes (CA-19)', function () {
    foreach (range(1, 4) as $n) {
        salidaConEntrevistaCompletada($this, ['valoraciones' => ['DIM-GENERAL' => $n]]);
    }

    $agregados = $this->getJson(URL_OFFBOARDING.'/agregados')->assertOk()->json('data');

    expect($agregados['oculto'])->toBeTrue()
        ->and($agregados['minimo'])->toBe(5)
        ->and($agregados['dimensiones'])->toBe([])
        ->and($agregados['categorias'])->toBe([])
        ->and($agregados['entrevistas'])->toBeNull();
});

it('con el mínimo de entrevistas calcula promedios y frecuencias deterministas y oculta las dimensiones con pocas respuestas (CA-19)', function () {
    foreach (range(1, 5) as $n) {
        $valoraciones = ['DIM-GENERAL' => $n];
        if ($n <= 2) {
            $valoraciones['DIM-CARGA'] = 5;
        }
        salidaConEntrevistaCompletada($this, ['valoraciones' => $valoraciones, 'categorias' => $n <= 3 ? ['CAT-COMPENSACION'] : []]);
    }

    $agregados = $this->getJson(URL_OFFBOARDING.'/agregados')->assertOk()->json('data');
    $general = collect($agregados['dimensiones'])->firstWhere('dimension', 'Experiencia general');
    $carga = collect($agregados['dimensiones'])->firstWhere('dimension', 'Carga laboral');
    $compensacion = collect($agregados['categorias'])->firstWhere('categoria', 'Compensación');

    expect($agregados['oculto'])->toBeFalse()
        ->and($agregados['entrevistas'])->toBe(5)
        ->and($general['respondieron'])->toBe(5)
        ->and($general['promedio'])->toEqual(3)
        ->and($general['distribucion'])->toBe([1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1])
        ->and($carga['respondieron'])->toBe(2)
        ->and($carga['promedio'])->toBeNull()
        ->and($carga['distribucion'])->toBeNull()
        ->and($compensacion['menciones'])->toBeNull();
});

it('los agregados no cuentan entrevistas de otro tenant ni de salidas canceladas', function () {
    salidaConEntrevistaCompletada($this, [], 2);
    $propia = salidaConEntrevistaCompletada($this);
    actuarComoAdminDeTenant();

    expect($this->getJson(URL_OFFBOARDING.'/agregados')->json('data.oculto'))->toBeTrue();

    $resumen = $this->getJson(URL_OFFBOARDING.'/resumen')->assertOk()->json('data');
    expect($resumen['entrevistas_completadas'])->toBe(1)
        ->and($resumen['programadas'])->toBe(1)
        ->and($propia['id'])->toBeGreaterThan(0);
});

it('el resumen cuenta salidas, cierres pendientes y estados de entrevista (HU-12)', function () {
    politicaVigente('Offboarding');
    salidaFormalizada($this);
    salidaFormalizada($this);
    salidaConEntrevistaCompletada($this);

    $resumen = $this->getJson(URL_OFFBOARDING.'/resumen')->assertOk()->json('data');

    expect($resumen)->toBe([
        'programadas' => 3, 'efectivas' => 0, 'con_incidencia' => 0, 'cierre_pendiente' => 0,
        'entrevistas_pendientes' => 2, 'entrevistas_completadas' => 1, 'entrevistas_no_realizadas' => 0,
    ]);
});
