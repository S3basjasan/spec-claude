<?php

use App\Modules\GestionPersonal\Domain\Services\CatalogoDePoliticas;
use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;
use App\Modules\GestionPersonal\Domain\Services\ReglasDePolitica;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

beforeEach(function () {
    $this->catalogo = new CatalogoDePoliticas;
    $this->reglas = new ReglasDePolitica($this->catalogo, new NormalizadorDeTexto);
});

it('cada tipo ofrece exactamente sus parámetros admitidos', function () {
    $claves = fn (TipoDePolitica $t) => $this->catalogo->claves($t);

    expect($claves(TipoDePolitica::Permisos))->toBe(['disponible', 'justificacion_obligatoria', 'documento_obligatorio', 'anticipacion_dias'])
        ->and($claves(TipoDePolitica::Licencias))->toBe($claves(TipoDePolitica::Permisos))
        ->and($claves(TipoDePolitica::Vacaciones))->toBe($claves(TipoDePolitica::Permisos))
        ->and($claves(TipoDePolitica::Ausencias))->toBe(['disponible', 'justificacion_obligatoria', 'documento_obligatorio'])
        ->and($claves(TipoDePolitica::Ascensos))->toBe(['disponible', 'anticipacion_dias', 'documento_adicional_obligatorio'])
        ->and($claves(TipoDePolitica::Traslados))->toBe($claves(TipoDePolitica::Ascensos))
        ->and($claves(TipoDePolitica::Offboarding))->toBe(['tareas']);
});

it('las reglas fijas se separan de los parámetros y cada categoría tiene las suyas', function () {
    $claves = fn (TipoDePolitica $t) => array_column($this->catalogo->reglasFijas($t), 'clave');

    expect($claves(TipoDePolitica::Ascensos))->toBe(['MOV-JUSTIFICACION', 'MOV-EVALUACION', 'MOV-DESTINO', 'MOV-UNA-POSICION', 'COM-AISLAMIENTO'])
        ->and($claves(TipoDePolitica::Offboarding))->toContain('OFF-MOTIVO-FECHA', 'OFF-SIN-RETROACTIVAS', 'OFF-SIN-RETENCION', 'OFF-RESPONSABLES')
        ->and($claves(TipoDePolitica::Permisos))->toContain('NOV-RESUELVE-ADMIN');
});

it('rechaza claves desconocidas y reglas fijas con mensajes distintos', function () {
    $errores = $this->reglas->erroresDeEdicion(TipoDePolitica::Ascensos, 'x', [
        'evaluacion_obligatoria' => false, 'justificacion_movimiento' => false, 'formula' => '1+1', 'disponible' => true,
    ]);

    expect($errores)->toHaveKeys(['parametros.evaluacion_obligatoria', 'parametros.justificacion_movimiento', 'parametros.formula'])
        ->not->toHaveKey('parametros.disponible')
        ->and($errores['parametros.evaluacion_obligatoria'][0])->toContain('Regla fija')->toContain('evaluación del colaborador')
        ->and($errores['parametros.justificacion_movimiento'][0])->toContain('justificación del movimiento')
        ->and($errores['parametros.formula'][0])->toBe('Parámetro no admitido para este tipo de política.');
});

it('Ausencias no admite anticipación mínima y lo explica', function () {
    $errores = $this->reglas->erroresDeEdicion(TipoDePolitica::Ausencias, 'x', ['anticipacion_dias' => 2]);

    expect($errores['parametros.anticipacion_dias'][0])->toBe('Este tipo de política no usa anticipación mínima.');
});

it('la anticipación debe ser un entero mayor o igual que cero', function () {
    foreach ([-1, 1.5, '2', null, true] as $invalido) {
        expect($this->reglas->erroresDeEdicion(TipoDePolitica::Permisos, 'x', ['anticipacion_dias' => $invalido]))->toHaveKey('parametros.anticipacion_dias');
    }
    foreach ([0, 1, 30] as $valido) {
        expect($this->reglas->erroresDeEdicion(TipoDePolitica::Permisos, 'x', ['anticipacion_dias' => $valido]))->toBe([]);
    }
});

it('los parámetros booleanos solo admiten verdadero o falso', function () {
    $errores = $this->reglas->erroresDeEdicion(TipoDePolitica::Permisos, 'x', ['disponible' => 'si', 'justificacion_obligatoria' => 1, 'documento_obligatorio' => false]);

    expect($errores)->toHaveKeys(['parametros.disponible', 'parametros.justificacion_obligatoria'])->not->toHaveKey('parametros.documento_obligatorio');
});

it('las tareas exigen nombre, únicos sin distinguir mayúsculas ni tildes, y máximo 30', function () {
    $tarea = fn (string $nombre) => ['nombre' => $nombre, 'descripcion' => null, 'exige_evidencia' => false];

    $errores = $this->reglas->erroresDeEdicion(TipoDePolitica::Offboarding, 'x', ['tareas' => [$tarea('Devolución de equipos'), $tarea('  '), $tarea('DEVOLUCION DE EQUIPOS')]]);
    expect($errores)->toHaveKeys(['parametros.tareas.1.nombre', 'parametros.tareas.2.nombre'])->not->toHaveKey('parametros.tareas.0.nombre');

    $treinta = array_map(fn ($i) => $tarea("Tarea $i"), range(1, 30));
    expect($this->reglas->erroresDeEdicion(TipoDePolitica::Offboarding, 'x', ['tareas' => $treinta]))->toBe([])
        ->and($this->reglas->erroresDeEdicion(TipoDePolitica::Offboarding, 'x', ['tareas' => [...$treinta, $tarea('Tarea 31')]]))->toHaveKey('parametros.tareas');
});

it('las tareas validan descripción, evidencia obligatoria y campos desconocidos', function () {
    $errores = $this->reglas->erroresDeEdicion(TipoDePolitica::Offboarding, 'x', ['tareas' => [
        ['nombre' => 'A', 'descripcion' => str_repeat('d', 501), 'exige_evidencia' => 'si', 'responsable' => 'Ana'],
        ['nombre' => str_repeat('n', 151), 'exige_evidencia' => true],
        'texto',
    ]]);

    expect($errores)->toHaveKeys([
        'parametros.tareas.0.descripcion', 'parametros.tareas.0.exige_evidencia', 'parametros.tareas.0.responsable',
        'parametros.tareas.1.nombre', 'parametros.tareas.2',
    ]);
});

it('respeta los límites de texto y recorta espacios', function () {
    expect($this->reglas->erroresDeEdicion(TipoDePolitica::Permisos, str_repeat('a', 1000), []))->toBe([])
        ->and($this->reglas->erroresDeEdicion(TipoDePolitica::Permisos, str_repeat('a', 1001), []))->toHaveKey('descripcion')
        ->and($this->reglas->texto("  hola \n"))->toBe('hola')
        ->and($this->reglas->texto(null))->toBe('')
        ->and($this->reglas->erroresDeRetiro(''))->toHaveKey('justificacion')
        ->and($this->reglas->erroresDeRetiro(str_repeat('j', 501)))->toHaveKey('justificacion')
        ->and($this->reglas->erroresDeRetiro(str_repeat('j', 500)))->toBe([]);
});

it('la publicación exige completitud, motivo y revisor', function () {
    $errores = $this->reglas->erroresDePublicacion(TipoDePolitica::Permisos, '', ['disponible' => true], '', '', '');

    expect($errores)->toHaveKeys(['descripcion', 'parametros.justificacion_obligatoria', 'parametros.documento_obligatorio', 'parametros.anticipacion_dias', 'motivo', 'revision.revisor'])
        ->and($this->reglas->erroresDePublicacion(TipoDePolitica::Permisos, 'Descripción', parametrosValidos('Permisos'), 'Motivo', 'Ana', ''))->toBe([]);
});

it('Offboarding exige la lista de tareas, que puede ser vacía', function () {
    expect($this->reglas->erroresDePublicacion(TipoDePolitica::Offboarding, 'D', [], 'm', 'r', ''))->toHaveKey('parametros.tareas')
        ->and($this->reglas->erroresDePublicacion(TipoDePolitica::Offboarding, 'D', ['tareas' => []], 'm', 'r', ''))->toBe([]);
});

it('los parámetros canónicos conservan el orden de las tareas, recortan textos y descartan vacíos', function () {
    $canonicos = $this->reglas->canonicos(TipoDePolitica::Offboarding, ['tareas' => [
        ['nombre' => ' Segunda ', 'descripcion' => '  ', 'exige_evidencia' => false],
        ['nombre' => 'Primera', 'descripcion' => ' con detalle ', 'exige_evidencia' => true],
    ]]);

    expect($canonicos['tareas'])->toBe([
        ['orden' => 1, 'nombre' => 'Segunda', 'descripcion' => null, 'exige_evidencia' => false],
        ['orden' => 2, 'nombre' => 'Primera', 'descripcion' => 'con detalle', 'exige_evidencia' => true],
    ]);
});

it('el sellado es determinista: mismo contenido, mismo JSON y mismo hash; otro contenido, otro hash', function () {
    $a = $this->reglas->sellar(TipoDePolitica::Permisos, 1, '2026-11-01T13:00:00Z', ['b' => 2, 'a' => 1]);
    $b = $this->reglas->sellar(TipoDePolitica::Permisos, 1, '2026-11-01T13:00:00Z', ['a' => 1, 'b' => 2]);
    $c = $this->reglas->sellar(TipoDePolitica::Permisos, 2, '2026-11-01T13:00:00Z', ['a' => 1, 'b' => 2]);

    expect($a['json'])->toBe($b['json'])->and($a['hash'])->toBe($b['hash'])->and($a['hash'])->not->toBe($c['hash'])
        ->and($a['hash'])->toHaveLength(64)->and($a['condiciones']['esquema'])->toBe(1);
});
