<?php

use App\Modules\Desarrollo\Domain\Entities\AccionDePdi;
use App\Modules\Desarrollo\Domain\Entities\CompromisoDePdi;
use App\Modules\Desarrollo\Domain\Entities\HitoDePdi;
use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\Exceptions\AcuerdoNoVigente;
use App\Modules\Desarrollo\Domain\Exceptions\PdiNoEditable;
use App\Modules\Desarrollo\Domain\Exceptions\TransicionInvalidaDePdi;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeAvance;
use App\Modules\Desarrollo\Domain\Services\HitosVencidos;
use App\Modules\Desarrollo\Domain\Services\ReglasDeCompletitudDePdi;
use App\Modules\Desarrollo\Domain\Services\ReglasDeTransicionDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\CodigoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeAccionPdi as EA;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeHito;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\PeriodoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;

function pdiDeDominio(EstadoDePdi $estado = EstadoDePdi::Borrador, bool $acuerdo = false, array $compromisos = [], int $responsable = 1, TipoDePdi $tipo = TipoDePdi::Mejora): Pdi
{
    return new Pdi(
        1, 1, 'PDI-0001', 10, $tipo, 'Objetivo', $tipo->requierePuestoObjetivo() ? 2 : null, null, null, $responsable,
        new PeriodoDePdi(new DateTimeImmutable('2026-07-01'), new DateTimeImmutable('2026-12-31')), $estado, 1,
        $acuerdo ? new DateTimeImmutable('2026-06-10') : null, $acuerdo ? 1 : null, null, null, null, null, null, null, 1, null,
        $compromisos, [],
    );
}

function pdiCompromisoDeDominio(array $acciones = []): CompromisoDePdi
{
    return new CompromisoDePdi(null, 1, DimensionDeCompromiso::Meta, null, null, 'Meta', null, null, null, new DateTimeImmutable('2026-07-01'), new DateTimeImmutable('2026-08-01'), null, $acciones);
}

function pdiAccionDeDominio(): AccionDePdi
{
    return new AccionDePdi(null, 1, TipoDeAccionPdi::Curso, 'x', null, new DateTimeImmutable('2026-07-01'), new DateTimeImmutable('2026-08-01'), null, EA::Pendiente);
}

it('solo se edita un plan en Borrador', function () {
    $reglas = new ReglasDeTransicionDePdi;

    $reglas->exigirEditable(pdiDeDominio());
    expect(fn () => $reglas->exigirEditable(pdiDeDominio(EstadoDePdi::Acordado)))->toThrow(PdiNoEditable::class)
        ->and(fn () => $reglas->exigirEditable(pdiDeDominio(EstadoDePdi::Cerrado)))->toThrow(PdiNoEditable::class);
});

it('el acuerdo del responsable exige Borrador y que no exista otro vigente', function () {
    $reglas = new ReglasDeTransicionDePdi;

    $reglas->exigirParaAcuerdo(pdiDeDominio());
    expect(fn () => $reglas->exigirParaAcuerdo(pdiDeDominio(acuerdo: true)))->toThrow(AcuerdoNoVigente::class)
        ->and(fn () => $reglas->exigirParaAcuerdo(pdiDeDominio(EstadoDePdi::Acordado)))->toThrow(PdiNoEditable::class);
});

it('el colaborador solo responde un Borrador con el acuerdo del responsable', function () {
    $reglas = new ReglasDeTransicionDePdi;

    $reglas->exigirParaRespuestaDelColaborador(pdiDeDominio(acuerdo: true));
    expect(fn () => $reglas->exigirParaRespuestaDelColaborador(pdiDeDominio()))->toThrow(AcuerdoNoVigente::class)
        ->and(fn () => $reglas->exigirParaRespuestaDelColaborador(pdiDeDominio(EstadoDePdi::Acordado, true)))->toThrow(TransicionInvalidaDePdi::class);
});

it('devolver a Borrador y cerrar solo desde Acordado; el seguimiento también', function () {
    $reglas = new ReglasDeTransicionDePdi;
    $acordado = pdiDeDominio(EstadoDePdi::Acordado, true);

    $reglas->exigirParaDevolverABorrador($acordado);
    $reglas->exigirParaCerrar($acordado);
    $reglas->exigirParaSeguimiento($acordado);
    foreach ([EstadoDePdi::Borrador, EstadoDePdi::Cerrado] as $otro) {
        expect(fn () => $reglas->exigirParaDevolverABorrador(pdiDeDominio($otro)))->toThrow(TransicionInvalidaDePdi::class)
            ->and(fn () => $reglas->exigirParaCerrar(pdiDeDominio($otro)))->toThrow(TransicionInvalidaDePdi::class)
            ->and(fn () => $reglas->exigirParaSeguimiento(pdiDeDominio($otro)))->toThrow(TransicionInvalidaDePdi::class);
    }
});

it('cambiar responsable y vincular se permiten en Borrador y Acordado, nunca en Cerrado', function () {
    $reglas = new ReglasDeTransicionDePdi;

    foreach ([EstadoDePdi::Borrador, EstadoDePdi::Acordado] as $estado) {
        $reglas->exigirParaCambiarResponsable(pdiDeDominio($estado));
        $reglas->exigirParaVincular(pdiDeDominio($estado));
    }
    expect(fn () => $reglas->exigirParaCambiarResponsable(pdiDeDominio(EstadoDePdi::Cerrado)))->toThrow(TransicionInvalidaDePdi::class)
        ->and(fn () => $reglas->exigirParaVincular(pdiDeDominio(EstadoDePdi::Cerrado)))->toThrow(TransicionInvalidaDePdi::class);
});

it('solo editar en Borrador borra los acuerdos', function () {
    $reglas = new ReglasDeTransicionDePdi;

    expect($reglas->borraAcuerdosAlEditar(pdiDeDominio()))->toBeTrue()
        ->and($reglas->borraAcuerdosAlEditar(pdiDeDominio(EstadoDePdi::Acordado, true)))->toBeFalse();
});

it('la completitud exige compromisos, acciones y un responsable administrador', function () {
    $reglas = new ReglasDeCompletitudDePdi;

    expect($reglas->faltantes(pdiDeDominio(), true))->toBe(['sin_compromisos'])
        ->and($reglas->faltantes(pdiDeDominio(compromisos: [pdiCompromisoDeDominio()]), true))->toBe(['compromiso_sin_acciones'])
        ->and($reglas->faltantes(pdiDeDominio(compromisos: [pdiCompromisoDeDominio([pdiAccionDeDominio()])]), true))->toBe([])
        ->and($reglas->faltantes(pdiDeDominio(compromisos: [pdiCompromisoDeDominio([pdiAccionDeDominio()])]), false))->toBe(['responsable_sin_permiso'])
        ->and($reglas->faltantes(pdiDeDominio(compromisos: [pdiCompromisoDeDominio([pdiAccionDeDominio()])], responsable: 0), true))->toBe(['sin_responsable']);
});

it('el avance es Cumplidas sobre no Canceladas y no es calculable si todas están Canceladas', function () {
    $calculador = new CalculadorDeAvance;

    expect($calculador->calcular([EA::Cancelada, EA::Cumplida, EA::Cumplida, EA::Pendiente]))->toBe(67)
        ->and($calculador->calcular([EA::Cumplida]))->toBe(100)
        ->and($calculador->calcular([EA::Pendiente, EA::EnCurso]))->toBe(0)
        ->and($calculador->calcular([EA::Cancelada, EA::Cancelada]))->toBeNull()
        ->and($calculador->calcular([]))->toBeNull();
});

it('un hito Pendiente con fecha anterior a hoy está vencido', function () {
    $hoy = new DateTimeImmutable('2026-06-15');
    $hito = fn (string $fecha, EstadoDeHito $e = EstadoDeHito::Pendiente) => new HitoDePdi(null, new DateTimeImmutable($fecha), 'x', $e, null);

    expect($hito('2026-06-14')->estaVencido($hoy))->toBeTrue()
        ->and($hito('2026-06-15')->estaVencido($hoy))->toBeFalse()
        ->and($hito('2026-06-01', EstadoDeHito::Realizado)->estaVencido($hoy))->toBeFalse()
        ->and((new HitosVencidos)->contar([$hito('2026-06-01'), $hito('2026-06-14'), $hito('2026-07-01')], $hoy))->toBe(2);
});

it('el código tiene el formato PDI-0001 y es secuencial', function () {
    expect(CodigoDePdi::desdeNumero(1)->valor())->toBe('PDI-0001')
        ->and(CodigoDePdi::desdeTexto('PDI-0042')->siguiente()->valor())->toBe('PDI-0043')
        ->and(fn () => CodigoDePdi::desdeTexto('PDI-1'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CodigoDePdi::desdeNumero(0))->toThrow(InvalidArgumentException::class);
});

it('el puesto objetivo es obligatorio en Carrera y Talento y no existe en Mejora', function () {
    expect(fn () => new Pdi(
        null, 1, null, 10, TipoDePdi::Carrera, 'x', null, null, null, 1,
        new PeriodoDePdi(new DateTimeImmutable('2026-07-01'), new DateTimeImmutable('2026-08-01')), EstadoDePdi::Borrador, 1,
        null, null, null, null, null, null, null, null, 1, null,
    ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new PeriodoDePdi(new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2026-07-01')))->toThrow(InvalidArgumentException::class);
});

it('un Borrador solo es visible para el colaborador con el acuerdo del responsable', function () {
    expect(pdiDeDominio()->visibleParaColaborador())->toBeFalse()
        ->and(pdiDeDominio(acuerdo: true)->visibleParaColaborador())->toBeTrue()
        ->and(pdiDeDominio(EstadoDePdi::Acordado)->visibleParaColaborador())->toBeTrue()
        ->and(pdiDeDominio(EstadoDePdi::Cerrado)->visibleParaColaborador())->toBeTrue();
});

it('la copia con cambios conserva el resto de los campos', function () {
    $copia = pdiDeDominio(acuerdo: true)->con(['version' => 5, 'acuerdoResponsableAt' => null]);

    expect($copia->version)->toBe(5)->and($copia->acuerdoResponsableAt)->toBeNull()->and($copia->codigo)->toBe('PDI-0001');
});
