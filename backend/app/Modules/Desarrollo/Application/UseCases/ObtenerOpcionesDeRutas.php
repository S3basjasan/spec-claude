<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Ports\PuestosParaRutasInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeRuta;
use Throwable;

/** Opciones del editor: tipos, departamentos activos y puestos activos. Lo que no responde se informa en `fuentes_no_disponibles`. */
final class ObtenerOpcionesDeRutas
{
    public function __construct(
        private readonly DepartamentosInterface $departamentos,
        private readonly PuestosParaRutasInterface $puestos,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(): array
    {
        $fuentes = [];
        try {
            $departamentos = $this->departamentos->activos();
        } catch (Throwable) {
            $departamentos = [];
            $fuentes[] = 'estructura';
        }
        try {
            $puestos = $this->puestos->activos();
        } catch (Throwable) {
            $puestos = [];
            $fuentes[] = 'estructura';
        }

        return [
            'tipos' => array_map(fn (TipoDeRuta $t) => ['valor' => $t->value, 'nombre' => $t->etiqueta(), 'admite_departamento' => $t->admiteDepartamento()], TipoDeRuta::cases()),
            'departamentos' => $departamentos,
            'puestos' => $puestos,
            'fuentes_no_disponibles' => array_values(array_unique($fuentes)),
        ];
    }
}
