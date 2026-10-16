<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoLaboral;

final class ObtenerFiltrosDeColaboradores
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly LectorEstructuraPort $estructura,
    ) {}

    /**
     * Opciones del listado: «Todos» y solo los estados que alguna ficha del tenant tiene; unidades publicadas.
     *
     * @return array{estados: list<array{valor: string}>, unidades: list<array{id: int, nombre: string}>, unidades_disponibles: bool}
     */
    public function ejecutar(ContextoDeTenant $contexto): array
    {
        $estados = [['valor' => 'Todos']];
        if ($this->colaboradores->hayFichas($contexto->tenantId)) {
            $estados[] = ['valor' => EstadoLaboral::SinVinculoVigente->value];
        }

        $unidades = $this->estructura->unidades();

        return [
            'estados' => $estados,
            'unidades' => $unidades->estaDisponible() ? array_values($unidades->datos) : [],
            'unidades_disponibles' => ! $unidades->fallo(),
        ];
    }
}
