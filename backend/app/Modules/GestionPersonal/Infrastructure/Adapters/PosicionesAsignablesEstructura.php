<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;
use Throwable;

final class PosicionesAsignablesEstructura implements PosicionesAsignablesPort
{
    public function listar(int $tenantId, ?string $texto, int $pagina, int $porPagina): array
    {
        try {
            /** @var array{items: list<array{id: int, puesto: string, unidad: string, jefatura: string|null}>, total: int} $resultado */
            $resultado = EstructuraFacade::posicionesAsignables($tenantId, $texto, $pagina, $porPagina);

            return $resultado;
        } catch (Throwable) {
            throw new IngresoNoVerificable;
        }
    }

    public function verificar(int $posicionId, int $tenantId): array
    {
        try {
            $veredicto = EstructuraFacade::posicionEsAsignable($posicionId, $tenantId);
            $posicion = EstructuraFacade::getPosicion($posicionId);
        } catch (Throwable) {
            throw new IngresoNoVerificable;
        }

        return [
            'asignable' => (bool) $veredicto['asignable'],
            'motivo' => $veredicto['motivo'],
            'posicion' => $posicion === null ? null : [
                'id' => (int) $posicion['id'],
                'puesto' => (string) $posicion['puesto'],
                'unidad' => $posicion['unidad'],
                'jefatura' => $posicion['jefatura'],
            ],
        ];
    }
}
