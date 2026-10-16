<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\ValidadorDePdfPort;
use App\Modules\GestionPersonal\Domain\Exceptions\ValidacionNoDisponible;
use App\Modules\GestionPersonal\Domain\ValueObjects\ResultadoDeValidacion;
use finfo;

/**
 * Valida un PDF por contenido y estructura sin ejecutar nada de él: tipo real, cabecera, `%%EOF`, `startxref`,
 * catálogo y objetos. No renderiza páginas ni interpreta JavaScript o acciones. No es un antivirus.
 */
final class ValidadorDePdfEstructural implements ValidadorDePdfPort
{
    private const VENTANA = 1024;

    public function validar(string $rutaTemporal): ResultadoDeValidacion
    {
        if (! is_file($rutaTemporal) || ! is_readable($rutaTemporal)) {
            throw new ValidacionNoDisponible;
        }

        $tamano = filesize($rutaTemporal);
        if ($tamano === false) {
            throw new ValidacionNoDisponible;
        }
        if ($tamano === 0) {
            return ResultadoDeValidacion::invalido('vacio');
        }

        $contenido = file_get_contents($rutaTemporal);
        if ($contenido === false) {
            throw new ValidacionNoDisponible;
        }

        $tipo = (new finfo(FILEINFO_MIME_TYPE))->buffer($contenido);
        if ($tipo !== 'application/pdf') {
            return ResultadoDeValidacion::invalido('no_es_pdf');
        }

        return $this->estructuraValida($contenido, $tamano)
            ? ResultadoDeValidacion::valido()
            : ResultadoDeValidacion::invalido('estructura_invalida');
    }

    private function estructuraValida(string $contenido, int $tamano): bool
    {
        $inicio = substr($contenido, 0, self::VENTANA);
        $final = substr($contenido, -self::VENTANA);

        if (! preg_match('/%PDF-[12]\.\d/', $inicio)) {
            return false;
        }

        if (! str_contains($final, '%%EOF')) {
            return false;
        }

        if (! preg_match_all('/startxref\s+(\d+)/', $final, $coincidencias) || $coincidencias[1] === []) {
            return false;
        }
        $desplazamiento = (int) end($coincidencias[1]);
        if ($desplazamiento < 0 || $desplazamiento >= $tamano) {
            return false;
        }
        $destino = substr($contenido, $desplazamiento, 64);
        if (! preg_match('/^(xref|\d+\s+\d+\s+obj)/', $destino)) {
            return false;
        }

        if (! str_contains($contenido, '/Catalog') && ! preg_match('/\/Root\s+\d+\s+\d+\s+R/', $contenido)) {
            return false;
        }

        return (bool) preg_match('/\d+\s+\d+\s+obj\b.*?\bendobj/s', $contenido);
    }
}
