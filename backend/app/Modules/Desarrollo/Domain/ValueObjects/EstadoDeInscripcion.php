<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Estado de la inscripción de un colaborador a una ruta. Finalizada y Retirada son terminales. */
enum EstadoDeInscripcion: string
{
    case Activa = 'Activa';
    case Finalizada = 'Finalizada';
    case Retirada = 'Retirada';
}
