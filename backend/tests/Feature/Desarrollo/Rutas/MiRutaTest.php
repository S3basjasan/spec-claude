<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;

/** Crea la ficha del colaborador con su cuenta, actúa como él y lo marca con vínculo vigente. */
function actuarComoColaboradorDeRuta(array $o = []): int
{
    [, $fichaId] = colaboradorConCuentaDePlan();
    rutasFalsos($o + ['vigentes' => [$fichaId => 'Diego Paredes', 11 => 'Luis Mora']]);

    return $fichaId;
}

it('devuelve la escalera con nivel actual, objetivo y siguiente, los requisitos y el aviso', function () {
    $ruta = rutaEnBd([1, 2, 3], 'Vigente');
    $yo = actuarComoColaboradorDeRuta();
    $inscripcion = inscripcionEnBd($ruta['id'], $yo, $ruta['niveles'][0], $ruta['niveles'][2]);

    $d = $this->getJson('/api/rutas/mi-ruta')->assertOk()->json('data');

    expect($d['aviso'])->toBe('Esta ruta es una guía de desarrollo; no implica la promesa de un puesto.')
        ->and($d['fuentes_no_disponibles'])->toBe([])
        ->and($d['inscripciones'])->toHaveCount(1);
    $i = $d['inscripciones'][0];
    expect($i['inscripcion_id'])->toBe($inscripcion->id)
        ->and($i['ruta'])->toBe(['id' => $ruta['id'], 'codigo' => $ruta['codigo'], 'nombre' => 'Ruta 1', 'tipo' => 'Especialista'])
        ->and($i['nivel_actual_id'])->toBe($ruta['niveles'][0])->and($i['nivel_objetivo_id'])->toBe($ruta['niveles'][2])
        ->and($i['siguiente_nivel_id'])->toBe($ruta['niveles'][1])
        ->and($i['puede_ver_adecuacion'])->toBeFalse()
        ->and(array_column($i['niveles'], 'orden'))->toBe([1, 2, 3])
        ->and($i['niveles'][2]['competencias'][0])->toMatchArray(['grado' => 'A', 'diferencial' => ['tipo' => 'sube', 'antes' => 'B', 'delta' => 1]])
        ->and($i['niveles'][2]['conocimientos'])->toBe(['informativo' => true, 'formacion' => ['Ingeniería'], 'capacitacion' => ['Excel avanzado', 'SQL']])
        ->and($i['niveles'][2]['experiencia']['anios_minimos'])->toEqual(2.0)
        ->and($i['niveles'][1])->not->toHaveKey('en_uso')->not->toHaveKey('inscritos_activos');
});

it('sin nivel actual, el siguiente nivel es el primero; en el último nivel no hay siguiente', function () {
    $ruta = rutaEnBd([1, 2, 3], 'Vigente');
    $otra = rutaEnBd([1, 2], 'Vigente');
    $yo = actuarComoColaboradorDeRuta();
    inscripcionEnBd($ruta['id'], $yo, null, $ruta['niveles'][1]);
    inscripcionEnBd($otra['id'], $yo, $otra['niveles'][1], null);

    $i = collect($this->getJson('/api/rutas/mi-ruta')->assertOk()->json('data.inscripciones'))->keyBy('ruta.id');

    expect($i[$ruta['id']]['siguiente_nivel_id'])->toBe($ruta['niveles'][0])->and($i[$otra['id']]['siguiente_nivel_id'])->toBeNull();
});

it('lista las rutas Vigentes disponibles y nunca las Borrador ni las Archivadas', function () {
    rutaEnBd([1, 2, 3], 'Vigente', ['nombre' => 'Vigente A']);
    rutaEnBd([1, 2], 'Borrador', ['nombre' => 'Borrador']);
    rutaEnBd([1, 2], 'Archivada', ['nombre' => 'Archivada']);
    rutaEnBd([1, 2], 'Vigente', ['nombre' => 'Vigente ajena'], tenantId: 2);
    actuarComoColaboradorDeRuta();

    $d = $this->getJson('/api/rutas/mi-ruta')->assertOk()->json('data');

    expect($d['inscripciones'])->toBe([])
        ->and(array_column($d['rutas_disponibles'], 'nombre'))->toBe(['Vigente A'])
        ->and($d['rutas_disponibles'][0])->toMatchArray(['tipo' => 'Especialista', 'niveles_count' => 3]);
});

it('un colaborador inscrito en una ruta Archivada sigue viendo su ruta', function () {
    $ruta = rutaEnBd([1, 2], 'Archivada');
    $yo = actuarComoColaboradorDeRuta();
    inscripcionEnBd($ruta['id'], $yo, $ruta['niveles'][0], $ruta['niveles'][1]);

    $this->getJson('/api/rutas/mi-ruta')->assertOk()->assertJsonCount(1, 'data.inscripciones');
});

it('solo devuelve sus inscripciones Activas, nunca las de otra persona ni las Retiradas o Finalizadas', function () {
    $ruta = rutaEnBd([1, 2, 3], 'Vigente');
    $yo = actuarComoColaboradorDeRuta();
    inscripcionEnBd($ruta['id'], 11, $ruta['niveles'][0], $ruta['niveles'][1]); // de otra persona
    inscripcionEnBd($ruta['id'], $yo, estado: 'Retirada', motivo: 'Cambio');
    inscripcionEnBd($ruta['id'], $yo, $ruta['niveles'][2], null, 'Finalizada');

    $this->getJson('/api/rutas/mi-ruta')->assertOk()->assertJsonCount(0, 'data.inscripciones');
});

it('sin ficha de colaborador responde vacío en lugar de error', function () {
    rutasFalsos();
    actuarComoUsuarioDeTenant();
    rutaEnBd([1, 2], 'Vigente');

    $d = $this->getJson('/api/rutas/mi-ruta')->assertOk()->json('data');

    expect($d['inscripciones'])->toBe([])->and($d['rutas_disponibles'])->toHaveCount(1);
});

it('concilia la baja antes de responder: una inscripción de quien ya no es vigente no aparece', function () {
    $ruta = rutaEnBd([1, 2], 'Vigente');
    [, $yo] = colaboradorConCuentaDePlan();
    rutasFalsos(['vigentes' => [11 => 'Otra persona']]); // el usuario ya no tiene vínculo vigente
    $inscripcion = inscripcionEnBd($ruta['id'], $yo, $ruta['niveles'][0], $ruta['niveles'][1]);

    $this->getJson('/api/rutas/mi-ruta')->assertOk()->assertJsonCount(0, 'data.inscripciones');

    expect(RutaInscripcionModel::query()->find($inscripcion->id))->estado->toBe('Retirada')->motivo->toBe('Baja del colaborador');
});

it('si Estructura no responde, devuelve la escalera propia con fuentes_no_disponibles y sin requisitos inventados', function () {
    $ruta = rutaEnBd([1, 2], 'Vigente');
    $yo = actuarComoColaboradorDeRuta(['falla' => true]);
    inscripcionEnBd($ruta['id'], $yo, $ruta['niveles'][0], $ruta['niveles'][1]);

    $d = $this->getJson('/api/rutas/mi-ruta')->assertOk()->assertJsonPath('data.fuentes_no_disponibles', ['estructura'])->json('data');

    expect($d['inscripciones'][0]['niveles'])->toHaveCount(2)
        ->and($d['inscripciones'][0]['niveles'][1]['competencias'])->toBe([])
        ->and($d['inscripciones'][0]['niveles'][1]['conocimientos'])->toBe(['informativo' => true, 'formacion' => [], 'capacitacion' => []]);
});

it('el administrador no usa Mi ruta y sin sesión no hay acceso', function () {
    actuarComoAdminDeTenant();

    $this->getJson('/api/rutas/mi-ruta')->assertForbidden();
});

it('el colaborador no accede a las rutas de gestión', function () {
    $ruta = rutaEnBd([1, 2], 'Vigente');
    actuarComoColaboradorDeRuta();

    $this->getJson('/api/rutas')->assertForbidden();
    $this->getJson("/api/rutas/{$ruta['id']}")->assertForbidden();
    $this->getJson('/api/inscripciones-ruta')->assertForbidden();
    $this->getJson("/api/rutas/{$ruta['id']}/historial")->assertForbidden();
});
