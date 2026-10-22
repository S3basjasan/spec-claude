<?php

namespace Tests\Support\Desarrollo;

use App\Modules\Desarrollo\Application\Ports\PuestosParaRutasInterface;
use RuntimeException;

/**
 * Puertos falso de puestos para rutas de carrera. `puestos` es `[id => perfil]` donde el perfil trae `codigo`, `nombre`,
 * `activo`, `competencias` (con grado) y `requisitos` de texto. Con `falla` simula Estructura caída.
 */
final class PuestosParaRutasFalso implements PuestosParaRutasInterface
{
    /** @var array<int, int> */
    public array $consultas = [];

    /**
     * @param  array<int, array<string, mixed>>  $puestos
     */
    public function __construct(public array $puestos = [], public bool $falla = false) {}

    public function perfil(int $puestoId): ?array
    {
        $this->exigirDisponible();
        $this->consultas[$puestoId] = ($this->consultas[$puestoId] ?? 0) + 1;
        $p = $this->puestos[$puestoId] ?? null;
        if ($p === null) {
            return null;
        }

        return [
            'puesto' => ['id' => $puestoId, 'codigo' => $p['codigo'], 'nombre' => $p['nombre'], 'activo' => $p['activo'] ?? true],
            'competencias' => $p['competencias'] ?? [],
            'requisitos' => ($p['requisitos'] ?? []) + ['formacion' => [], 'capacitacion' => [], 'experiencia' => []],
        ];
    }

    public function activos(): array
    {
        $this->exigirDisponible();
        $r = [];
        foreach ($this->puestos as $id => $p) {
            if ($p['activo'] ?? true) {
                $r[] = ['id' => $id, 'codigo' => $p['codigo'], 'nombre' => $p['nombre']];
            }
        }

        return $r;
    }

    public function estaActivo(int $puestoId): bool
    {
        $this->exigirDisponible();

        return isset($this->puestos[$puestoId]) && ($this->puestos[$puestoId]['activo'] ?? true);
    }

    private function exigirDisponible(): void
    {
        if ($this->falla) {
            throw new RuntimeException('Estructura Empresarial no disponible');
        }
    }
}
