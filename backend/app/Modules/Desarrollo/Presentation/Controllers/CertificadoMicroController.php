<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\GenerarCertificado;
use App\Modules\Desarrollo\Domain\Exceptions\ErrorDeMicro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CertificadoMicroController
{
    use RespondeErroresDeMicro;

    public function show(int $id, Request $request, GenerarCertificado $generar): Response|JsonResponse
    {
        try {
            $this->exigirColaborador($request);
            $pdf = $generar->ejecutar($id, $this->usuario($request));
        } catch (ErrorDeMicro $e) {
            return $this->responder(fn () => throw $e);
        }

        return response($pdf['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['nombre'].'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
