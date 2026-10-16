<?php

use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;

it('dispone de Normalizer y unifica NFD con NFC', function () {
    $nfd = "e\u{0301}";
    $nfc = "\u{00E9}";

    expect(class_exists(Normalizer::class))->toBeTrue()
        ->and((new NormalizadorDeTexto)->claveDeIdentificacion($nfd))->toBe((new NormalizadorDeTexto)->claveDeIdentificacion($nfc));
});

it('la clave de identificación recorta, usa minúsculas y conserva ceros, separadores y tildes', function () {
    $n = new NormalizadorDeTexto;

    expect($n->claveDeIdentificacion('  0102-03 '))->toBe('0102-03')
        ->and($n->claveDeIdentificacion('0102-03'))->not->toBe($n->claveDeIdentificacion('010203'))
        ->and($n->claveDeIdentificacion('AB123'))->toBe($n->claveDeIdentificacion(' ab123 '))
        ->and($n->claveDeIdentificacion('00123'))->toBe('00123')
        ->and($n->claveDeIdentificacion('Ñandú'))->toBe('ñandú');
});

it('el texto de búsqueda ignora tildes y mayúsculas', function () {
    $n = new NormalizadorDeTexto;

    expect($n->busquedaDeTexto('  Núñez '))->toBe('nunez')
        ->and($n->busquedaDeTexto('GESTIÓN'))->toBe('gestion');
});
