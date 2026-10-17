<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\DTO\CertificadoDTO;
use App\Modules\Desarrollo\Application\Ports\CertificadoPdfInterface;
use App\Modules\Desarrollo\Domain\Exceptions\CertificadoNoDisponible;
use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;

/**
 * Render en memoria con dompdf. Sin PHP embebido, sin recursos remotos ni archivos locales; todos los datos se
 * escapan con `e()`, por lo que no se interpreta HTML ni CSS de usuario.
 */
final class CertificadoPdfDompdf implements CertificadoPdfInterface
{
    public function renderizar(CertificadoDTO $certificado): string
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
            $dompdf->loadHtml($this->html($certificado), 'UTF-8');
            $dompdf->render();

            $contenido = (string) $dompdf->output();
        } catch (Throwable $excepcion) {
            report($excepcion);
            throw new CertificadoNoDisponible;
        }

        if ($contenido === '' || ! str_starts_with($contenido, '%PDF-')) {
            throw new CertificadoNoDisponible;
        }

        return $contenido;
    }

    private function html(CertificadoDTO $c): string
    {
        $competencias = $c->competencias === []
            ? 'Sin competencias asociadas'
            : implode(', ', array_map(fn (string $n) => e($n), $c->competencias));

        $fecha = e(substr($c->fechaFinalizacion, 0, 10));
        $duracion = $c->duracionMinutos >= 60
            ? intdiv($c->duracionMinutos, 60).' h '.($c->duracionMinutos % 60).' min'
            : $c->duracionMinutos.' min';

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><style>'
            .'body{font-family:"DejaVu Sans",sans-serif;color:#12171D;text-align:center;margin:0;padding:40px 60px}'
            .'.marco{border:4px solid #0F6F69;padding:36px 40px}'
            .'h1{font-size:28px;color:#0F6F69;margin:0 0 6px}'
            .'.sub{font-size:13px;color:#5A6573;margin:0 0 28px}'
            .'.nombre{font-size:26px;font-weight:bold;margin:8px 0 18px}'
            .'.titulo{font-size:20px;font-weight:bold;margin:8px 0 18px}'
            .'.dato{font-size:13px;color:#3B4654;margin:6px 0}'
            .'.pie{font-size:11px;color:#5A6573;margin-top:30px}'
            .'</style></head><body><div class="marco">'
            .'<h1>Certificado de finalización</h1>'
            .'<p class="sub">Micro-learning · '.e($c->codigo).'</p>'
            .'<p class="dato">Se certifica que</p>'
            .'<p class="nombre">'.e($c->colaborador).'</p>'
            .'<p class="dato">completó el micro-contenido</p>'
            .'<p class="titulo">'.e($c->titulo).'</p>'
            .'<p class="dato">Competencias asociadas: '.$competencias.'</p>'
            .'<p class="dato">Duración: '.e($duracion).'</p>'
            .'<p class="dato">Fecha de finalización: '.$fecha.'</p>'
            .'<p class="pie">Los micro-contenidos refuerzan conocimientos; no reemplazan el desarrollo de competencias conductuales.</p>'
            .'</div></body></html>';
    }
}
