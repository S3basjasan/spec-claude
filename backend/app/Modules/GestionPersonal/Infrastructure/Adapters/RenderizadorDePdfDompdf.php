<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\DTO\SnapshotDeFichaDTO;
use App\Modules\GestionPersonal\Application\Ports\RenderizadorDePdfPort;
use App\Modules\GestionPersonal\Domain\Exceptions\ExportacionNoDisponible;
use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;

/**
 * Render en memoria con dompdf. Sin PHP embebido, sin recursos remotos y con la raíz de archivos restringida a las
 * plantillas; los datos se escapan en la vista (`{{ }}`), por lo que no se interpreta HTML ni CSS de usuario.
 */
final class RenderizadorDePdfDompdf implements RenderizadorDePdfPort
{
    public function renderizar(SnapshotDeFichaDTO $snapshot): string
    {
        try {
            $plantillas = dirname(__DIR__).'/Pdf/plantillas';
            $cache = storage_path('framework/cache/dompdf');
            if (! is_dir($cache)) {
                @mkdir($cache, 0775, true);
            }

            $opciones = new Options;
            $opciones->set('isRemoteEnabled', false);
            $opciones->set('isPhpEnabled', false);
            $opciones->set('isJavascriptEnabled', false);
            $opciones->set('chroot', $plantillas);
            $opciones->set('defaultFont', 'DejaVu Sans');
            $opciones->set('fontCache', $cache);
            $opciones->set('tempDir', $cache);

            $html = view('gp-exportacion::ficha', ['s' => $snapshot])->render();

            $dompdf = new Dompdf($opciones);
            $dompdf->setPaper('A4');
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->render();

            $canvas = $dompdf->getCanvas();
            $fuente = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
            $ancho = $canvas->get_width();
            $alto = $canvas->get_height();
            $canvas->page_text(50, $alto - 40, 'Ficha '.$snapshot->codigo.' · Generado el '.$snapshot->generadoEn, $fuente, 8, [0.32, 0.38, 0.45]);
            $canvas->page_text($ancho - 130, $alto - 40, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 8, [0.32, 0.38, 0.45]);

            $contenido = (string) $dompdf->output();
        } catch (Throwable $excepcion) {
            report($excepcion);
            throw new ExportacionNoDisponible;
        }

        if ($contenido === '' || ! str_starts_with($contenido, '%PDF-') || ! str_contains(substr($contenido, -1024), '%%EOF')) {
            throw new ExportacionNoDisponible;
        }

        return $contenido;
    }
}
