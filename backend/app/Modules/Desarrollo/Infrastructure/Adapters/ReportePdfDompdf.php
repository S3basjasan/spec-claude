<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\GeneradorDeReportePdfInterface;
use App\Modules\Desarrollo\Domain\Exceptions\ExportacionNoDisponible;
use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;

/**
 * PDF en memoria con dompdf: indicadores y tablas, sin gráficos. Sin PHP embebido, sin recursos remotos ni archivos
 * locales; todos los datos se escapan con `e()`.
 */
final class ReportePdfDompdf implements GeneradorDeReportePdfInterface
{
    public function generar(array $datos): string
    {
        try {
            $cache = storage_path('framework/cache/dompdf');
            if (! is_dir($cache)) {
                @mkdir($cache, 0775, true);
            }

            $opciones = new Options;
            $opciones->set('isRemoteEnabled', false);
            $opciones->set('isPhpEnabled', false);
            $opciones->set('isJavascriptEnabled', false);
            $opciones->set('chroot', $cache);
            $opciones->set('defaultFont', 'DejaVu Sans');
            $opciones->set('fontCache', $cache);
            $opciones->set('tempDir', $cache);

            $dompdf = new Dompdf($opciones);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->loadHtml($this->html($datos), 'UTF-8');
            $dompdf->render();

            $contenido = (string) $dompdf->output();
        } catch (Throwable $excepcion) {
            report($excepcion);
            throw new ExportacionNoDisponible;
        }

        if ($contenido === '' || ! str_starts_with($contenido, '%PDF-')) {
            throw new ExportacionNoDisponible;
        }

        return $contenido;
    }

    /** @param  array{titulo: string, subtitulo: string, tablas: list<array{titulo: string, columnas: list<string>, filas: list<list<string|int|float|bool|null>>}>}  $datos */
    private function html(array $datos): string
    {
        $tablas = '';
        foreach ($datos['tablas'] as $tabla) {
            $encabezado = implode('', array_map(fn (string $c) => '<th>'.e($c).'</th>', $tabla['columnas']));
            $filas = $tabla['filas'] === []
                ? '<tr><td colspan="'.max(1, count($tabla['columnas'])).'" class="vacio">Sin datos para los filtros elegidos.</td></tr>'
                : implode('', array_map(
                    fn (array $fila) => '<tr>'.implode('', array_map(fn ($v) => '<td>'.e($this->texto($v)).'</td>', array_values($fila))).'</tr>',
                    $tabla['filas'],
                ));
            $tablas .= '<h2>'.e($tabla['titulo']).'</h2><table><thead><tr>'.$encabezado.'</tr></thead><tbody>'.$filas.'</tbody></table>';
        }

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><style>'
            .'body{font-family:"DejaVu Sans",sans-serif;font-size:9px;color:#12171d}h1{font-size:16px;margin:0}h2{font-size:12px;margin:14px 0 4px}'
            .'p{margin:2px 0 8px;color:#5a6573}table{width:100%;border-collapse:collapse}th,td{border:1px solid #c9ced6;padding:3px 4px;text-align:left}'
            .'th{background:#e3f2f0}.vacio{color:#5a6573}</style></head><body>'
            .'<h1>'.e($datos['titulo']).'</h1><p>'.e($datos['subtitulo']).'</p>'.$tablas.'</body></html>';
    }

    private function texto(mixed $valor): string
    {
        return match (true) {
            $valor === null => '',
            is_bool($valor) => $valor ? 'Sí' : 'No',
            default => (string) $valor,
        };
    }
}
