<?php

use App\Modules\Estructura\Domain\Services\ComparadorDeCambiosDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\CambioAuditado;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaAsociada;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPuesto;
use App\Modules\Estructura\Domain\ValueObjects\FuncionPuesto;
use App\Modules\Estructura\Domain\ValueObjects\InstantaneaDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\RequisitoPuesto;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoFuncion;
use App\Modules\Estructura\Domain\ValueObjects\TipoRequisito;

function unaFuncion(int $orden, string $descripcion, TipoFuncion $tipo = TipoFuncion::Esencial): FuncionPuesto
{
    return new FuncionPuesto($orden, $descripcion, $tipo);
}

function unaCompetenciaDePuesto(int $id, string $grado, string $nombre = 'Trabajo en equipo'): CompetenciaDePuesto
{
    return new CompetenciaDePuesto(
        new CompetenciaAsociada($id, sprintf('COMP-%04d', $id), $nombre, 'Descripción', TipoCompetencia::Cardinal, EstadoCompetencia::Activo),
        $grado,
    );
}

/**
 * @param  array<int, FuncionPuesto>|null  $funciones
 * @param  array<int, RequisitoPuesto>|null  $requisitos
 * @param  array<int, CompetenciaDePuesto>|null  $competencias
 */
function unaInstantanea(
    string $nombre = 'Analista de datos',
    ?array $funciones = null,
    ?array $requisitos = null,
    ?array $competencias = null,
    EstadoPuesto $estado = EstadoPuesto::Activo,
    string $descripcion = 'Convierte datos en reportes.',
    string $mision = 'Apoyar las decisiones.',
): InstantaneaDePuesto {
    return new InstantaneaDePuesto(
        'PU-001',
        $nombre,
        $descripcion,
        $mision,
        $estado,
        $funciones ?? [unaFuncion(1, 'Recopilar datos.'), unaFuncion(2, 'Documentar indicadores.', TipoFuncion::Complementaria)],
        $requisitos ?? [new RequisitoPuesto(TipoRequisito::Formacion, 'Título afín.'), new RequisitoPuesto(TipoRequisito::Experiencia, '2 años.')],
        $competencias ?? [unaCompetenciaDePuesto(2, 'B')],
    );
}

/**
 * @param  array<int, CambioAuditado>  $cambios
 * @return array<int, array{0: string, 1: ?string, 2: ?string}>
 */
function cambiosComoArreglo(array $cambios): array
{
    return array_map(fn (CambioAuditado $c) => [$c->campo, $c->valorAnterior, $c->valorNuevo], $cambios);
}

beforeEach(function () {
    $this->comparador = new ComparadorDeCambiosDePuesto;
});

it('registra la creación con todas las filas y el valor anterior nulo', function () {
    $cambios = cambiosComoArreglo($this->comparador->comparar(null, unaInstantanea()));

    expect($cambios)->toBe([
        ['codigo', null, 'PU-001'],
        ['nombre', null, 'Analista de datos'],
        ['descripcion', null, 'Convierte datos en reportes.'],
        ['mision', null, 'Apoyar las decisiones.'],
        ['estado', null, 'Activo'],
        ['funciones', null, 'Esencial: Recopilar datos.'],
        ['funciones', null, 'Complementaria: Documentar indicadores.'],
        ['requisitos', null, 'Formación: Título afín.'],
        ['requisitos', null, 'Experiencia: 2 años.'],
        ['competencias', null, 'COMP-0002 Trabajo en equipo: grado B'],
    ]);
});

it('registra un cambio de nombre, descripción y misión', function () {
    $cambios = cambiosComoArreglo($this->comparador->comparar(
        unaInstantanea(),
        unaInstantanea('Analista senior', descripcion: 'Otra síntesis.', mision: 'Otra misión.'),
    ));

    expect($cambios)->toBe([
        ['nombre', 'Analista de datos', 'Analista senior'],
        ['descripcion', 'Convierte datos en reportes.', 'Otra síntesis.'],
        ['mision', 'Apoyar las decisiones.', 'Otra misión.'],
    ]);
});

it('registra el cambio de estado', function () {
    $cambios = cambiosComoArreglo($this->comparador->comparar(unaInstantanea(), unaInstantanea(estado: EstadoPuesto::Inactivo)));

    expect($cambios)->toBe([['estado', 'Activo', 'Inactivo']]);
});

it('registra el alta, la baja y el cambio de tipo de una función', function () {
    $anterior = unaInstantanea(funciones: [unaFuncion(1, 'Recopilar datos.'), unaFuncion(2, 'Documentar.', TipoFuncion::Complementaria)]);

    $alta = cambiosComoArreglo($this->comparador->comparar($anterior, unaInstantanea(funciones: [
        unaFuncion(1, 'Recopilar datos.'), unaFuncion(2, 'Documentar.', TipoFuncion::Complementaria), unaFuncion(3, 'Capacitar al equipo.', TipoFuncion::Complementaria),
    ])));
    $baja = cambiosComoArreglo($this->comparador->comparar($anterior, unaInstantanea(funciones: [unaFuncion(1, 'Recopilar datos.')])));
    $tipo = cambiosComoArreglo($this->comparador->comparar($anterior, unaInstantanea(funciones: [
        unaFuncion(1, 'Recopilar datos.'), unaFuncion(2, 'Documentar.', TipoFuncion::Esencial),
    ])));

    expect($alta)->toBe([['funciones', null, 'Complementaria: Capacitar al equipo.']])
        ->and($baja)->toBe([['funciones', 'Complementaria: Documentar.', null]])
        ->and($tipo)->toBe([['funciones', 'Complementaria: Documentar.', 'Esencial: Documentar.']]);
});

it('registra el cambio del texto de una función como una baja y un alta', function () {
    $cambios = cambiosComoArreglo($this->comparador->comparar(
        unaInstantanea(funciones: [unaFuncion(1, 'Recopilar datos.')]),
        unaInstantanea(funciones: [unaFuncion(1, 'Consolidar datos de varias fuentes.')]),
    ));

    expect($cambios)->toBe([
        ['funciones', 'Esencial: Recopilar datos.', null],
        ['funciones', null, 'Esencial: Consolidar datos de varias fuentes.'],
    ]);
});

it('registra un cambio que solo difiere en mayúsculas como una modificación', function () {
    $cambios = cambiosComoArreglo($this->comparador->comparar(
        unaInstantanea(funciones: [unaFuncion(1, 'recopilar datos.')]),
        unaInstantanea(funciones: [unaFuncion(1, 'Recopilar datos.')]),
    ));

    expect($cambios)->toBe([['funciones', 'Esencial: recopilar datos.', 'Esencial: Recopilar datos.']]);
});

it('no audita el orden de las funciones ni la numeración derivada', function () {
    $anterior = unaInstantanea(funciones: [unaFuncion(1, 'A'), unaFuncion(2, 'B'), unaFuncion(3, 'C')]);

    $reordenadas = $this->comparador->comparar($anterior, unaInstantanea(funciones: [unaFuncion(1, 'C'), unaFuncion(2, 'A'), unaFuncion(3, 'B')]));
    $sinUna = cambiosComoArreglo($this->comparador->comparar($anterior, unaInstantanea(funciones: [unaFuncion(1, 'A'), unaFuncion(2, 'C')])));

    expect($reordenadas)->toBe([])
        ->and($sinUna)->toBe([['funciones', 'Esencial: B', null]]);
});

it('trata los requisitos: cambiar el texto es baja y alta, y el mismo texto en tipos distintos son elementos distintos', function () {
    $anterior = unaInstantanea(requisitos: [new RequisitoPuesto(TipoRequisito::Formacion, 'Título afín.')]);

    $texto = cambiosComoArreglo($this->comparador->comparar($anterior, unaInstantanea(requisitos: [new RequisitoPuesto(TipoRequisito::Formacion, 'Maestría afín.')])));
    $tipo = cambiosComoArreglo($this->comparador->comparar($anterior, unaInstantanea(requisitos: [new RequisitoPuesto(TipoRequisito::Capacitacion, 'Título afín.')])));

    expect($texto)->toBe([
        ['requisitos', 'Formación: Título afín.', null],
        ['requisitos', null, 'Formación: Maestría afín.'],
    ])->and($tipo)->toBe([
        ['requisitos', 'Formación: Título afín.', null],
        ['requisitos', null, 'Capacitación: Título afín.'],
    ]);
});

it('registra el alta, la baja y el cambio de grado de una competencia', function () {
    $anterior = unaInstantanea(competencias: [unaCompetenciaDePuesto(2, 'B'), unaCompetenciaDePuesto(3, 'C', 'Liderazgo')]);

    $alta = cambiosComoArreglo($this->comparador->comparar($anterior, unaInstantanea(competencias: [
        unaCompetenciaDePuesto(2, 'B'), unaCompetenciaDePuesto(3, 'C', 'Liderazgo'), unaCompetenciaDePuesto(4, 'A', 'Comunicación'),
    ])));
    $baja = cambiosComoArreglo($this->comparador->comparar($anterior, unaInstantanea(competencias: [unaCompetenciaDePuesto(2, 'B')])));
    $grado = cambiosComoArreglo($this->comparador->comparar($anterior, unaInstantanea(competencias: [unaCompetenciaDePuesto(2, 'A'), unaCompetenciaDePuesto(3, 'C', 'Liderazgo')])));

    expect($alta)->toBe([['competencias', null, 'COMP-0004 Comunicación: grado A']])
        ->and($baja)->toBe([['competencias', 'COMP-0003 Liderazgo: grado C', null]])
        ->and($grado)->toBe([['competencias', 'COMP-0002 Trabajo en equipo: grado B', 'COMP-0002 Trabajo en equipo: grado A']]);
});

it('no devuelve cambios si no hay diferencias', function () {
    expect($this->comparador->comparar(unaInstantanea(), unaInstantanea()))->toBe([]);
});

it('compara las claves sin distinguir mayúsculas ni espacios alrededor', function () {
    $cambios = $this->comparador->comparar(
        unaInstantanea(funciones: [unaFuncion(1, 'Recopilar datos.')]),
        unaInstantanea(funciones: [unaFuncion(1, '  Recopilar datos.  ')]),
    );

    expect($cambios)->toBe([]);
});
