<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\GeneradorDeExcelInterface;
use App\Modules\Desarrollo\Domain\Exceptions\ExportacionNoDisponible;
use ZipArchive;

/**
 * Escribe un libro .xlsx mínimo (SpreadsheetML) con ZipArchive, sin librerías de hojas de cálculo. Los textos van como
 * cadenas en línea y se escapan para XML; los números, como numéricos. La primera fila de cada hoja se muestra en negrita.
 */
final class ExcelConZip implements GeneradorDeExcelInterface
{
    private const PROHIBIDOS = ['\\', '/', '?', '*', '[', ']', ':'];

    public function generar(array $hojas): string
    {
        if (! class_exists(ZipArchive::class) || $hojas === []) {
            throw new ExportacionNoDisponible;
        }

        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($ruta === false || $zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new ExportacionNoDisponible;
        }

        try {
            $nombres = $this->nombresDeHojas($hojas);
            $zip->addFromString('[Content_Types].xml', $this->tiposDeContenido(count($hojas)));
            $zip->addFromString('_rels/.rels', $this->relacionesRaiz());
            $zip->addFromString('xl/workbook.xml', $this->libro($nombres));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relacionesDelLibro(count($hojas)));
            $zip->addFromString('xl/styles.xml', $this->estilos());
            foreach ($hojas as $i => $hoja) {
                $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', $this->hoja($hoja['filas']));
            }
            $zip->close();
            $contenido = file_get_contents($ruta);
        } finally {
            @unlink($ruta);
        }

        if ($contenido === false || $contenido === '') {
            throw new ExportacionNoDisponible;
        }

        return $contenido;
    }

    /**
     * @param  list<array{nombre: string, filas: array<int, mixed>}>  $hojas
     * @return list<string>
     */
    private function nombresDeHojas(array $hojas): array
    {
        $usados = [];
        $nombres = [];
        foreach ($hojas as $hoja) {
            $base = trim(str_replace(self::PROHIBIDOS, ' ', $hoja['nombre']));
            $base = mb_substr($base === '' ? 'Hoja' : $base, 0, 31);
            $nombre = $base;
            for ($n = 2; in_array(mb_strtolower($nombre), $usados, true); $n++) {
                $nombre = mb_substr($base, 0, 28).' '.$n;
            }
            $usados[] = mb_strtolower($nombre);
            $nombres[] = $nombre;
        }

        return $nombres;
    }

    private function tiposDeContenido(int $hojas): string
    {
        $overrides = '';
        for ($i = 1; $i <= $hojas; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides.'</Types>';
    }

    private function relacionesRaiz(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    /** @param  list<string>  $nombres */
    private function libro(array $nombres): string
    {
        $hojas = '';
        foreach ($nombres as $i => $nombre) {
            $hojas .= '<sheet name="'.$this->xml($nombre).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$hojas.'</sheets></workbook>';
    }

    private function relacionesDelLibro(int $hojas): string
    {
        $r = '';
        for ($i = 1; $i <= $hojas; $i++) {
            $r .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }
        $r .= '<Relationship Id="rId'.($hojas + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$r.'</Relationships>';
    }

    private function estilos(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'</styleSheet>';
    }

    /** @param  list<list<string|int|float|bool|null>>  $filas */
    private function hoja(array $filas): string
    {
        $xml = '';
        foreach ($filas as $r => $fila) {
            $celdas = '';
            foreach (array_values($fila) as $c => $valor) {
                $ref = $this->columna($c).($r + 1);
                $estilo = $r === 0 ? ' s="1"' : '';
                if ($valor === null || $valor === '') {
                    continue;
                }
                if (is_bool($valor)) {
                    $valor = $valor ? 'Sí' : 'No';
                }
                $celdas .= is_int($valor) || is_float($valor)
                    ? '<c r="'.$ref.'"'.$estilo.'><v>'.$this->numero($valor).'</v></c>'
                    : '<c r="'.$ref.'" t="inlineStr"'.$estilo.'><is><t xml:space="preserve">'.$this->xml((string) $valor).'</t></is></c>';
            }
            $xml .= '<row r="'.($r + 1).'">'.$celdas.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xml.'</sheetData></worksheet>';
    }

    private function columna(int $indice): string
    {
        $letra = '';
        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letra = chr(65 + ($n - 1) % 26).$letra;
        }

        return $letra;
    }

    private function numero(int|float $valor): string
    {
        return is_int($valor) ? (string) $valor : rtrim(rtrim(number_format($valor, 6, '.', ''), '0'), '.');
    }

    private function xml(string $texto): string
    {
        $limpio = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $texto) ?? '';

        return htmlspecialchars($limpio, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
