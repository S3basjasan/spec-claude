<?php

namespace App\Modules\Desarrollo\Application\Ports;

/** Si una competencia tiene al menos un curso o micro-contenido disponible en el Catálogo Formativo. */
interface OfertaFormativaInterface
{
    public function tieneOferta(int $competenciaId): bool;
}
