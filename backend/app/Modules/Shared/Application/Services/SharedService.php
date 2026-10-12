<?php

namespace App\Modules\Shared\Application\Services;

/**
 * Servicio inter-módulos del Módulo Shared.
 *
 * C4 Nivel 3 — clase real detrás de [Component: Laravel Facade].
 * Expande con métodos get* que delegan a Application\UseCases.
 */
class SharedService
{
    /**
     * Resumen mínimo para verificar el cableado Facade -> Servicio.
     *
     * @return array<string, mixed>
     */
    public function getResumen(): array
    {
        return ['modulo' => 'Shared'];
    }
}
