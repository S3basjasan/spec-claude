<?php

namespace Database\Seeders\Support;

/** PDF mínimo y estructuralmente válido para datos de ejemplo: sin datos personales ni contenido real. */
final class PdfDeEjemplo
{
    public static function contenido(string $texto): string
    {
        $texto = str_replace(['(', ')', '\\'], ' ', $texto);
        $flujo = "BT /F1 14 Tf 72 720 Td ({$texto}) Tj ET";
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            4 => '<< /Length '.strlen($flujo)." >>\nstream\n{$flujo}\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $desplazamientos = [];
        foreach ($objetos as $numero => $cuerpo) {
            $desplazamientos[$numero] = strlen($pdf);
            $pdf .= "{$numero} 0 obj\n{$cuerpo}\nendobj\n";
        }

        $xref = strlen($pdf);
        $total = count($objetos) + 1;
        $pdf .= "xref\n0 {$total}\n0000000000 65535 f \n";
        foreach ($desplazamientos as $desplazamiento) {
            $pdf .= sprintf("%010d 00000 n \n", $desplazamiento);
        }

        return $pdf."trailer\n<< /Size {$total} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
