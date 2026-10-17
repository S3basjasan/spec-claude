<?php

namespace App\Modules\Desarrollo\Application\Ports;

/** Nombre completo de un colaborador para el certificado (Gestión de Personal es la única fuente). */
interface NombreDeColaboradorInterface
{
    /** Nombres y apellidos, o null si la ficha no existe. */
    public function nombreDe(int $tenantId, int $colaboradorId): ?string;
}
