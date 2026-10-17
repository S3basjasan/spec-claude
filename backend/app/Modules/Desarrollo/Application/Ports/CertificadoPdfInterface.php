<?php

namespace App\Modules\Desarrollo\Application\Ports;

use App\Modules\Desarrollo\Application\DTO\CertificadoDTO;

/** Genera los bytes del PDF de un certificado de finalización. */
interface CertificadoPdfInterface
{
    public function renderizar(CertificadoDTO $certificado): string;
}
