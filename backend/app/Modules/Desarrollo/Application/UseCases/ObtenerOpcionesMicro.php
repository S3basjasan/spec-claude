<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;

/** Competencias para el formulario (solo activas) y para el filtro del repositorio (todas). */
final class ObtenerOpcionesMicro
{
    public function __construct(private readonly CompetenciasInterface $competencias) {}

    /**
     * @return array{competencias_activas: array<int, array{id: int, codigo: string, nombre: string}>, competencias: array<int, array{id: int, codigo: string, nombre: string, estado: string}>}
     */
    public function ejecutar(): array
    {
        $todas = array_map(fn (array $c) => [
            'id' => (int) $c['id'],
            'codigo' => (string) $c['codigo'],
            'nombre' => (string) $c['nombre'],
            'estado' => (string) $c['estado'],
        ], array_values($this->competencias->todas()));

        return [
            'competencias_activas' => array_values(array_map(
                fn (array $c) => ['id' => $c['id'], 'codigo' => $c['codigo'], 'nombre' => $c['nombre']],
                array_filter($todas, fn (array $c) => $c['estado'] === 'Activo'),
            )),
            'competencias' => $todas,
        ];
    }
}
