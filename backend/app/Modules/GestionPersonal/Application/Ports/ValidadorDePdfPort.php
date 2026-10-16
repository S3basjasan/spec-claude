<?php

namespace App\Modules\GestionPersonal\Application\Ports;

use App\Modules\GestionPersonal\Domain\Exceptions\ValidacionNoDisponible;
use App\Modules\GestionPersonal\Domain\ValueObjects\ResultadoDeValidacion;

interface ValidadorDePdfPort
{
    /**
     * Comprueba por contenido que el archivo es un PDF; no renderiza ni ejecuta nada de él.
     *
     * @throws ValidacionNoDisponible Si no pudo leerse por una limitación técnica (distinto de «inválido»).
     */
    public function validar(string $rutaTemporal): ResultadoDeValidacion;
}
