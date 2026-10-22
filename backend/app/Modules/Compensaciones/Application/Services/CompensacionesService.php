<?php

namespace App\Modules\Compensaciones\Application\Services;

/**
 * Servicio inter-módulos del Módulo Compensaciones.
 *
 * C4 Nivel 3 — clase real detrás de [Component: Laravel Facade].
 * Expande con métodos get* que delegan a Application\UseCases.
 */
class CompensacionesService
{
    /**
     * Resumen mínimo para verificar el cableado Facade -> Servicio.
     *
     * @return array<string, mixed>
     */
    public function getResumen(): array
    {
        return ['modulo' => 'Compensaciones'];
    }
}
