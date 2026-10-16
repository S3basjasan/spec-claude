<?php

use App\Modules\GestionPersonal\Domain\Services\EtiquetasDeExportacion;

beforeEach(fn () => $this->etiquetas = new EtiquetasDeExportacion);

it('un dato ausente es No registrado y el cero se conserva', function () {
    expect($this->etiquetas->valor(null))->toBe('No registrado')
        ->and($this->etiquetas->valor('  '))->toBe('No registrado')
        ->and($this->etiquetas->valor(0))->toBe('0')
        ->and($this->etiquetas->valor('Quito'))->toBe('Quito');
});

it('formatea fechas como DD/MM/AAAA y los nulos como No registrado', function () {
    expect($this->etiquetas->fecha('2026-10-05'))->toBe('05/10/2026')
        ->and($this->etiquetas->fecha(null))->toBe('No registrado');
});

it('traduce estados laborales, de formación y de movilidad', function () {
    expect($this->etiquetas->estadoLaboral('SinVinculoVigente'))->toBe('Sin vínculo vigente')
        ->and($this->etiquetas->estadoLaboral('Activo'))->toBe('Activo')
        ->and($this->etiquetas->estadoLaboral('Desvinculado'))->toBe('Desvinculado')
        ->and($this->etiquetas->estadoDeFormacion('EnCurso'))->toBe('En curso')
        ->and($this->etiquetas->estadoDeMovilidad('NoInformada'))->toBe('No informada')
        ->and($this->etiquetas->estadoDeMovilidad('NoDisponible'))->toBe('No disponible');
});

it('el nombre de archivo se sanea a caracteres seguros', function () {
    expect($this->etiquetas->nombreDeArchivo('EMP-001'))->toBe('Ficha_EMP-001.pdf')
        ->and($this->etiquetas->nombreDeArchivo("EMP/../0 1\"\r\n"))->toBe('Ficha_EMP01.pdf')
        ->and($this->etiquetas->nombreDeArchivo('///'))->toBe('Ficha_SIN-CODIGO.pdf');
});
