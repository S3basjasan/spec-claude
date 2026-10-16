<?php

use App\Modules\GestionPersonal\Domain\Exceptions\ValidacionNoDisponible;
use App\Modules\GestionPersonal\Infrastructure\Adapters\ValidadorDePdfEstructural;

function archivoTemporal(string $contenido): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($ruta, $contenido);

    return $ruta;
}

function motivoDe(string $contenido): ?string
{
    $ruta = archivoTemporal($contenido);
    $resultado = (new ValidadorDePdfEstructural)->validar($ruta);
    unlink($ruta);

    return $resultado->valido ? null : $resultado->motivo;
}

it('acepta un PDF estructuralmente válido de una y de varias páginas', function () {
    expect(motivoDe(pdfSintetico()))->toBeNull()
        ->and(motivoDe(pdfSintetico(5)))->toBeNull();
});

it('rechaza un archivo vacío', function () {
    expect(motivoDe(''))->toBe('vacio');
});

it('rechaza una imagen o un texto renombrados a .pdf', function () {
    expect(motivoDe(archivoNoPdf()))->toBe('no_es_pdf')
        ->and(motivoDe('texto plano cualquiera'))->toBe('no_es_pdf')
        ->and(motivoDe("MZ\x90\x00 ejecutable"))->toBe('no_es_pdf');
});

it('rechaza un PDF truncado o dañado', function () {
    $pdf = pdfSintetico();

    expect(motivoDe(substr($pdf, 0, (int) (strlen($pdf) / 2))))->toBe('estructura_invalida')
        ->and(motivoDe(str_replace('%%EOF', '', $pdf)))->toBe('estructura_invalida')
        ->and(motivoDe(str_replace('startxref', 'startxxxx', $pdf)))->toBe('estructura_invalida');
});

it('rechaza un startxref fuera de rango o que no apunta a una tabla', function () {
    $pdf = pdfSintetico();
    $fuera = preg_replace('/startxref\s+\d+/', 'startxref 99999999', $pdf);
    $malo = preg_replace('/startxref\s+\d+/', 'startxref 12', $pdf);

    expect(motivoDe($fuera))->toBe('estructura_invalida')
        ->and(motivoDe($malo))->toBe('estructura_invalida');
});

it('rechaza un archivo sin catálogo', function () {
    $sinCatalogo = str_replace(['/Type /Catalog', '/Root 1 0 R'], ['/Type /Otro', '/Raiz 1 0 R'], pdfSintetico());

    expect(motivoDe($sinCatalogo))->toBe('estructura_invalida');
});

it('un archivo ilegible es una limitación técnica distinta de inválido', function () {
    expect(fn () => (new ValidadorDePdfEstructural)->validar('/ruta/que/no/existe.pdf'))->toThrow(ValidacionNoDisponible::class);
});
