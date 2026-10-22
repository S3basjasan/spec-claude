<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\PuestosParaRutasInterface;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use LogicException;

/**
 * Lee los puestos solo por la Facade pública de Estructura Empresarial. Expone competencias con grado y los requisitos
 * de texto del puesto; la bandera `desarrollo.conocimientos_con_grado` es un punto de extensión sin rama activa.
 */
final class PuestosParaRutasDesdeEstructura implements PuestosParaRutasInterface
{
    /** @var array<int, array<string, mixed>|null> */
    private array $memo = [];

    public function perfil(int $puestoId): ?array
    {
        $this->exigirSinConocimientosConGrado();

        if (! array_key_exists($puestoId, $this->memo)) {
            $this->memo[$puestoId] = $this->leer($puestoId);
        }

        return $this->memo[$puestoId];
    }

    public function activos(): array
    {
        $this->exigirSinConocimientosConGrado();

        return array_values(array_map(
            fn (array $p) => ['id' => (int) $p['id'], 'codigo' => (string) ($p['codigo'] ?? ''), 'nombre' => (string) $p['nombre']],
            EstructuraFacade::getPuestosActivos(),
        ));
    }

    public function estaActivo(int $puestoId): bool
    {
        return EstructuraFacade::puestoEstaActivo($puestoId);
    }

    /** @return array<string, mixed>|null */
    private function leer(int $puestoId): ?array
    {
        $puesto = EstructuraFacade::getPuesto($puestoId);
        if ($puesto === null) {
            return null;
        }

        $requisitos = ['formacion' => [], 'capacitacion' => [], 'experiencia' => []];
        foreach ($puesto['requisitos'] as $r) {
            $clave = match ($r['tipo']) {
                'Formacion' => 'formacion',
                'Capacitacion' => 'capacitacion',
                'Experiencia' => 'experiencia',
                default => null,
            };
            if ($clave !== null) {
                $requisitos[$clave][] = (string) $r['descripcion'];
            }
        }

        return [
            'puesto' => [
                'id' => (int) $puesto['id'],
                'codigo' => (string) ($puesto['codigo'] ?? ''),
                'nombre' => (string) $puesto['nombre'],
                'activo' => $puesto['estado'] === 'Activo',
            ],
            'competencias' => array_values(array_map(fn (array $c) => [
                'competencia_id' => (int) $c['competencia_id'],
                'codigo' => (string) $c['codigo'],
                'nombre' => (string) $c['nombre'],
                'tipo' => (string) $c['tipo'],
                'grado' => (string) $c['grado_requerido'],
            ], $puesto['competencias'])),
            'requisitos' => $requisitos,
        ];
    }

    private function exigirSinConocimientosConGrado(): void
    {
        if (config('desarrollo.conocimientos_con_grado', false) === true) {
            throw new LogicException('Los conocimientos con grado no están implementados: la bandera desarrollo.conocimientos_con_grado debe ser false.');
        }
    }
}
